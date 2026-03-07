<?php

declare(strict_types=1);

namespace Controller;

use OCA\TeamsGovernance\AppInfo\Application;
use OCA\TeamsGovernance\Controller\ApiController;
use OCA\TeamsGovernance\Service\TeamsService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	public function testIndex(): void {
		$request = $this->createMock(IRequest::class);
		$teamsService = $this->createMock(TeamsService::class);
		$teamsService->method('listUserTeams')
			->willReturn([
				'available' => true,
				'message' => '',
				'rows' => [
					[
						'id' => 'abc123',
						'name' => 'Platform Team',
						'creator' => 'Alice',
						'creatorUserId' => 'alice',
						'createdAt' => 1710000000,
						'membersCount' => 6,
						'hasDetails' => true,
						'hasTeamShare' => null,
					],
				],
				'total' => 1,
			]);

		$controller = new ApiController(Application::APP_ID, $request, $teamsService, 'admin');
		$data = $controller->index()->getData();

		$this->assertTrue($data['available']);
		$this->assertSame(1, $data['pagination']['total']);
		$this->assertSame('Platform Team', $data['rows'][0]['name']);
	}

	public function testIndexForwardsSearchTerm(): void {
		$request = $this->createMock(IRequest::class);
		$teamsService = $this->createMock(TeamsService::class);

		$teamsService->expects($this->once())
			->method('listUserTeams')
			->with('rober', 1, 25)
			->willReturn([
				'available' => true,
				'message' => '',
				'rows' => [],
				'total' => 0,
			]);

		$controller = new ApiController(Application::APP_ID, $request, $teamsService, 'admin');
		$controller->index(1, 25, 'rober');
	}

	public function testDetailsForwardsTeamIdAndUser(): void {
		$request = $this->createMock(IRequest::class);
		$teamsService = $this->createMock(TeamsService::class);

		$teamsService->expects($this->once())
			->method('getTeamDetails')
			->with('team-123', 'admin')
			->willReturn([
				'available' => true,
				'message' => '',
				'team' => [
					'id' => 'team-123',
					'name' => 'Platform Team',
					'creator' => 'Alice',
					'creatorUserId' => 'alice',
					'createdAt' => 1710000000,
					'membersCount' => 6,
					'hasTeamShare' => false,
					'resourcesSummary' => ['total' => 0, 'byProvider' => []],
					'resources' => [],
				],
			]);

		$controller = new ApiController(Application::APP_ID, $request, $teamsService, 'admin');
		$data = $controller->details('team-123')->getData();

		$this->assertTrue($data['available']);
		$this->assertSame('team-123', $data['team']['id']);
	}
}
