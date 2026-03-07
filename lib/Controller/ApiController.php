<?php

declare(strict_types=1);

namespace OCA\TeamsGovernance\Controller;

use OCA\TeamsGovernance\Service\TeamsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AdminRequired;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private TeamsService $teamsService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List user-based teams for governance
	 *
	 * @return DataResponse<Http::STATUS_OK, array{available: bool, message: string, rows: list<array{id: string, name: string, creator: string, creatorUserId: string, createdAt: int|null, membersCount: int, hasDetails: bool, hasTeamShare: bool|null}>, pagination: array{page: int, limit: int, total: int, totalPages: int}}, array{}>
	 *
	 * 200: Data returned
	 */
	#[AdminRequired]
	#[ApiRoute(verb: 'GET', url: '/admin/teams')]
	public function index(int $page = 1, int $limit = 25, string $search = ''): DataResponse {
		$normalizedPage = max(1, $page);
		$normalizedLimit = min(100, max(1, $limit));
		$result = $this->teamsService->listUserTeams($search, $normalizedPage, $normalizedLimit);
		$totalPages = $result['total'] > 0 ? (int)ceil($result['total'] / $normalizedLimit) : 0;

		return new DataResponse(
			[
				'available' => $result['available'],
				'message' => $result['message'],
				'rows' => $result['rows'],
				'pagination' => [
					'page' => $normalizedPage,
					'limit' => $normalizedLimit,
					'total' => $result['total'],
					'totalPages' => $totalPages,
				],
			]
		);
	}

	/**
	 * Team details including connected resources
	 *
	 * @return DataResponse<Http::STATUS_OK, array{available: bool, message: string, team: array{id: string, name: string, creator: string, creatorUserId: string, createdAt: int|null, membersCount: int, hasTeamShare: bool, resourcesSummary: array{total: int, byProvider: array<string, int>}, resources: list<array{id: string, label: string, url: string, iconSvg: string|null, iconURL: string|null, iconEmoji: string|null, provider: array{id: string, name: string, icon: string}}>}|null}, array{}>
	 *
	 * 200: Data returned
	 */
	#[AdminRequired]
	#[ApiRoute(verb: 'GET', url: '/admin/teams/{teamId}')]
	public function details(string $teamId): DataResponse {
		return new DataResponse(
			$this->teamsService->getTeamDetails($teamId, $this->userId)
		);
	}
}
