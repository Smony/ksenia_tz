<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Exceptions\NoAvailableOperatorException;
use App\Exceptions\TelephonyDispatchException;
use App\Models\Call;
use App\Services\Telephony\TelephonyGateway;
use Illuminate\Support\Facades\DB;

class IncomingCallProcessor
{
    public function __construct(
        private readonly ClientResolver $clientResolver,
        private readonly OperatorSelector $operatorSelector,
        private readonly TelephonyGateway $telephony,
        private readonly CallProcessingLogger $logger,
    ) {
    }

    public function process(int $callId): void
    {
        $call = Call::query()->findOrFail($callId);

        if ($call->status === CallStatus::Assigned && $call->operator_id !== null) {
            $this->logger->info($call, 'idempotent_skip', 'Call already assigned, skipping');

            return;
        }

        DB::transaction(function () use ($call) {
            $locked = Call::query()->whereKey($call->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CallStatus::Assigned && $locked->operator_id !== null) {
                return;
            }

            $locked->update(['status' => CallStatus::Processing]);
        });

        $call->refresh();

        try {
            $this->runPipeline($call);
        } catch (NoAvailableOperatorException $e) {
            $call->update(['status' => CallStatus::Incoming]);
            $this->logger->warning($call, 'no_operator', $e->getMessage());
            throw $e;
        } catch (TelephonyDispatchException $e) {
            $this->rollbackAssignment($call);
            $this->logger->error($call, 'telephony_failed', $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            $this->rollbackAssignment($call);
            $this->logger->error($call, 'unexpected', $e->getMessage(), [
                'exception' => $e::class,
            ]);
            throw $e;
        }
    }

    private function runPipeline(Call $call): void
    {
        $client = $this->clientResolver->resolveForCall($call);

        if ($client !== null) {
            $call->update(['client_id' => $client->id]);
            $this->logger->info($call, 'client_resolved', 'Client matched by phone', [
                'client_id' => $client->id,
            ]);
        } else {
            $this->logger->info($call, 'client_resolved', 'Client not found, proceeding without link');
        }

        $operator = $this->operatorSelector->reserveNext();

        $this->logger->info($call, 'operator_reserved', 'Operator reserved', [
            'operator_id' => $operator->id,
        ]);

        $call->update([
            'operator_id' => $operator->id,
            'status' => CallStatus::Assigned,
            'assigned_at' => now(),
        ]);

        try {
            $this->telephony->dispatchCallAssigned($call->fresh(), $operator);
        } catch (TelephonyDispatchException $e) {
            throw $e;
        }

        $this->logger->info($call, 'telephony_dispatched', 'Telephony event sent', [
            'operator_id' => $operator->id,
        ]);
    }

    private function rollbackAssignment(Call $call): void
    {
        DB::transaction(function () use ($call) {
            $locked = Call::query()->whereKey($call->id)->lockForUpdate()->first();

            if ($locked === null || $locked->operator_id === null) {
                return;
            }

            $operator = $locked->operator;
            $operatorId = $locked->operator_id;

            $locked->update([
                'operator_id' => null,
                'status' => CallStatus::Incoming,
                'assigned_at' => null,
            ]);

            if ($operator !== null) {
                $this->operatorSelector->release($operator);
            }

            $this->logger->warning($locked, 'rollback', 'Assignment rolled back', [
                'operator_id' => $operatorId,
            ]);
        });

        $call->refresh();
    }
}
