<?php

namespace Tests\Unit\Ocpp;

use App\Services\Ocpp\WebSocket\Frame;
use PHPUnit\Framework\TestCase;

class FrameTest extends TestCase
{
    public function test_round_trip_for_all_length_encodings(): void
    {
        foreach ([0, 5, 125, 126, 65535, 65536, 200000] as $length) {
            $payload = str_repeat('x', $length);
            foreach ([false, true] as $mask) {
                $buffer = Frame::encode($payload, Frame::OP_TEXT, $mask);
                $frame = Frame::decode($buffer, 1_000_000);

                $this->assertNotNull($frame);
                $this->assertSame($payload, $frame->payload);
                $this->assertSame(Frame::OP_TEXT, $frame->opcode);
                $this->assertTrue($frame->fin);
                $this->assertSame('', $buffer);
            }
        }
    }

    public function test_partial_buffer_waits_for_more_data(): void
    {
        $full = Frame::encode('{"a":1}', Frame::OP_TEXT, true);
        $buffer = substr($full, 0, 5);

        $this->assertNull(Frame::decode($buffer, 1000));
        $this->assertSame(5, strlen($buffer));

        $buffer .= substr($full, 5);
        $this->assertSame('{"a":1}', Frame::decode($buffer, 1000)?->payload);
    }

    public function test_rejects_oversized_frames(): void
    {
        $buffer = Frame::encode(str_repeat('x', 2000));

        $this->expectException(\RuntimeException::class);
        Frame::decode($buffer, 1000);
    }

    public function test_multiple_frames_in_one_buffer(): void
    {
        $buffer = Frame::encode('one', Frame::OP_TEXT, true).Frame::encode('two', Frame::OP_TEXT, true);

        $this->assertSame('one', Frame::decode($buffer, 100)?->payload);
        $this->assertSame('two', Frame::decode($buffer, 100)?->payload);
        $this->assertNull(Frame::decode($buffer, 100));
    }
}
