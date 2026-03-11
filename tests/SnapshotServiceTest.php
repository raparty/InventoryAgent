<?php

declare(strict_types=1);

namespace InventoryAgent\Tests;

use InventoryAgent\SnapshotService;
use PHPUnit\Framework\TestCase;

class SnapshotServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers: build lightweight mysqli/stmt/result mocks
    // -----------------------------------------------------------------------

    /**
     * Creates a mysqli stub whose prepare() returns a statement stub that
     * will yield $row from get_result()->fetch_assoc().
     *
     * @param array<string, mixed>|null $row
     */
    private function buildMysqliMock(?array $row): \mysqli
    {
        $resultMock = $this->createMock(\mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn($row);

        $stmtMock = $this->getMockBuilder(\mysqli_stmt::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stmtMock->method('bind_param')->willReturn(true);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('get_result')->willReturn($resultMock);

        $mysqliMock = $this->getMockBuilder(\mysqli::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mysqliMock->method('prepare')->willReturn($stmtMock);

        return $mysqliMock;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testReturnsDecodedJsonWhenRowExists(): void
    {
        $payload = ['hostname' => 'PC-001', 'os_name' => 'Windows 11'];
        $mysqliMock = $this->buildMysqliMock(['raw_json' => json_encode($payload)]);

        $service = new SnapshotService($mysqliMock);
        $result  = $service->getSnapshot(42, 7);

        $this->assertSame($payload, $result);
    }

    public function testReturnsEmptyArrayWhenNoRowFound(): void
    {
        $mysqliMock = $this->buildMysqliMock(null);

        $service = new SnapshotService($mysqliMock);
        $result  = $service->getSnapshot(99, 1);

        $this->assertSame([], $result);
    }

    public function testReturnsEmptyArrayForInvalidJson(): void
    {
        $mysqliMock = $this->buildMysqliMock(['raw_json' => 'not-valid-json']);

        $service = new SnapshotService($mysqliMock);
        $result  = $service->getSnapshot(1, 1);

        $this->assertSame([], $result);
    }

    public function testPrepareSqlContainsDeviceHistory(): void
    {
        $resultMock = $this->createMock(\mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn(null);

        $stmtMock = $this->getMockBuilder(\mysqli_stmt::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('get_result')->willReturn($resultMock);

        $mysqliMock = $this->getMockBuilder(\mysqli::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mysqliMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('device_history'))
            ->willReturn($stmtMock);

        $service = new SnapshotService($mysqliMock);
        $service->getSnapshot(5, 3);
    }
}
