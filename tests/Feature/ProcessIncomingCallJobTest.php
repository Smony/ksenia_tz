<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\OperatorStatus;
use App\Exceptions\NoAvailableOperatorException;
use App\Jobs\ProcessIncomingCallJob;
use App\Models\Call;
use App\Models\CallProcessingLog;
use App\Models\Client;
use App\Models\Operator;
use App\Services\IncomingCallProcessor;
use App\Services\Telephony\TelephonyGateway;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeTelephonyGateway;
use Tests\TestCase;

class ProcessIncomingCallJobTest extends TestCase
{
    private FakeTelephonyGateway $telephony;

    protected function setUp(): void
    {
        parent::setUp();

        $this->telephony = new FakeTelephonyGateway;
        $this->app->instance(TelephonyGateway::class, $this->telephony);
    }

    public function test_assigns_client_operator_and_dispatches_telephony(): void
    {
        $client = Client::create(['phone' => '79001234567', 'name' => 'Ivan']);
        $operator = Operator::create([
            'name' => 'Op 1',
            'status' => OperatorStatus::Available,
            'max_concurrent_calls' => 2,
        ]);

        $call = Call::create([
            'phone' => '+7 (900) 123-45-67',
            'status' => CallStatus::Incoming,
        ]);

        app(IncomingCallProcessor::class)->process($call->id);

        $call->refresh();
        $operator->refresh();

        $this->assertSame($client->id, $call->client_id);
        $this->assertSame($operator->id, $call->operator_id);
        $this->assertSame(CallStatus::Assigned, $call->status);
        $this->assertNotNull($call->assigned_at);
        $this->assertSame(1, $this->telephony->dispatched);
        $this->assertSame(1, $operator->active_calls);

        $steps = CallProcessingLog::where('call_id', $call->id)->pluck('step')->all();
        $this->assertContains('client_resolved', $steps);
        $this->assertContains('operator_reserved', $steps);
        $this->assertContains('telephony_dispatched', $steps);
    }

    public function test_second_run_is_idempotent(): void
    {
        Operator::create(['name' => 'Op 1', 'status' => OperatorStatus::Available]);
        $call = Call::create(['phone' => '79001112233', 'status' => CallStatus::Incoming]);

        $processor = app(IncomingCallProcessor::class);
        $processor->process($call->id);
        $processor->process($call->id);

        $call->refresh();

        $this->assertSame(1, $this->telephony->dispatched);
        $this->assertSame(1, Operator::first()->active_calls);
        $this->assertTrue(
            CallProcessingLog::where('call_id', $call->id)
                ->where('step', 'idempotent_skip')
                ->exists()
        );
    }

    public function test_parallel_calls_get_different_operators(): void
    {
        Operator::create(['name' => 'Op 1', 'status' => OperatorStatus::Available]);
        Operator::create(['name' => 'Op 2', 'status' => OperatorStatus::Available]);

        $callA = Call::create(['phone' => '79001111111', 'status' => CallStatus::Incoming]);
        $callB = Call::create(['phone' => '79002222222', 'status' => CallStatus::Incoming]);

        $processor = app(IncomingCallProcessor::class);

        DB::transaction(function () use ($processor, $callA, $callB) {
            $processor->process($callA->id);
            $processor->process($callB->id);
        });

        $callA->refresh();
        $callB->refresh();

        $this->assertNotNull($callA->operator_id);
        $this->assertNotNull($callB->operator_id);
        $this->assertNotSame($callA->operator_id, $callB->operator_id);
    }

    public function test_throws_when_no_operators_available(): void
    {
        $call = Call::create(['phone' => '79003334455', 'status' => CallStatus::Incoming]);

        $this->expectException(NoAvailableOperatorException::class);

        app(IncomingCallProcessor::class)->process($call->id);
    }

    public function test_rolls_back_operator_on_telephony_failure(): void
    {
        $this->telephony->shouldFail = true;

        Operator::create(['name' => 'Op 1', 'status' => OperatorStatus::Available]);
        $call = Call::create(['phone' => '79004445566', 'status' => CallStatus::Incoming]);

        try {
            app(IncomingCallProcessor::class)->process($call->id);
        } catch (\App\Exceptions\TelephonyDispatchException) {
            // expected
        }

        $call->refresh();
        $operator = Operator::first();

        $this->assertNull($call->operator_id);
        $this->assertSame(CallStatus::Incoming, $call->status);
        $this->assertSame(0, $operator->active_calls);
        $this->assertSame(OperatorStatus::Available, $operator->status);
    }

    public function test_job_declares_retry_and_uniqueness(): void
    {
        $job = new ProcessIncomingCallJob(42);

        $this->assertSame(5, $job->tries);
        $this->assertSame('42', $job->uniqueId());
        $this->assertSame('incoming-calls', $job->queue);
    }
}
