<?php

declare(strict_types=1);

namespace OCA\TeamsGovernance\Service;

use OCP\Teams\ITeamManager;
use OCP\Teams\TeamResource;
use OCP\Server;
use Throwable;

class TeamsService {
	/**
	 * @return array{available: bool, message: string, rows: list<array{id: string, name: string, creator: string, creatorUserId: string, createdAt: int|null, membersCount: int, hasDetails: bool, hasTeamShare: bool|null}>, total: int}
	 */
	public function listUserTeams(string $search = '', int $page = 1, int $limit = 25): array {
		$normalizedPage = max(1, $page);
		$normalizedLimit = min(100, max(1, $limit));

		try {
			$allRows = $this->fetchRowsFromCircles();
		} catch (Throwable) {
			return [
				'available' => false,
				'message' => 'Teams data is unavailable. Make sure the Teams app is enabled and up to date.',
				'rows' => [],
				'total' => 0,
			];
		}

		$filteredRows = $this->filterRows($allRows, $search);
		$total = count($filteredRows);
		$offset = ($normalizedPage - 1) * $normalizedLimit;

		return [
			'available' => true,
			'message' => '',
			'rows' => array_map(
				static fn (array $row): array => [
					'id' => $row['id'],
					'name' => $row['name'],
					'creator' => $row['creator'],
					'creatorUserId' => $row['creatorUserId'],
					'createdAt' => $row['createdAt'],
					'membersCount' => $row['membersCount'],
					'hasDetails' => $row['id'] !== '',
					'hasTeamShare' => null,
				],
				array_slice($filteredRows, $offset, $normalizedLimit)
			),
			'total' => $total,
		];
	}

	/**
	 * @return array{available: bool, message: string, team: array{id: string, name: string, creator: string, creatorUserId: string, createdAt: int|null, membersCount: int, hasTeamShare: bool, resourcesSummary: array{total: int, byProvider: array<string, int>}, resources: list<array{id: string, label: string, url: string, iconSvg: string|null, iconURL: string|null, iconEmoji: string|null, provider: array{id: string, name: string, icon: string}>}}|null}
	 */
	public function getTeamDetails(string $teamId, ?string $userId): array {
		$normalizedTeamId = trim($teamId);
		if ($normalizedTeamId === '') {
			return [
				'available' => false,
				'message' => 'Team id is required.',
				'team' => null,
			];
		}

		try {
			$allRows = $this->fetchRowsFromCircles();
		} catch (Throwable) {
			return [
				'available' => false,
				'message' => 'Teams data is unavailable. Make sure the Teams app is enabled and up to date.',
				'team' => null,
			];
		}

		$teamRow = null;
		foreach ($allRows as $row) {
			if ($row['id'] === $normalizedTeamId) {
				$teamRow = $row;
				break;
			}
		}

		if ($teamRow === null) {
			return [
				'available' => false,
				'message' => 'Team not found.',
				'team' => null,
			];
		}

		$resources = $this->fetchTeamResources($normalizedTeamId, $userId);
		$byProvider = [];
		$hasTeamShare = false;

		foreach ($resources as $resource) {
			$providerId = strtolower((string)($resource['provider']['id'] ?? ''));
			if ($providerId === '') {
				$providerId = 'unknown';
			}

			$byProvider[$providerId] = ($byProvider[$providerId] ?? 0) + 1;

			if (str_starts_with($providerId, 'files')) {
				$hasTeamShare = true;
			}
		}

		return [
			'available' => true,
			'message' => '',
			'team' => [
				'id' => $teamRow['id'],
				'name' => $teamRow['name'],
				'creator' => $teamRow['creator'],
				'creatorUserId' => $teamRow['creatorUserId'],
				'createdAt' => $teamRow['createdAt'],
				'membersCount' => $teamRow['membersCount'],
				'hasTeamShare' => $hasTeamShare,
				'resourcesSummary' => [
					'total' => count($resources),
					'byProvider' => $byProvider,
				],
				'resources' => $resources,
			],
		];
	}

	/**
	 * @return list<array{id: string, name: string, creator: string, creatorUserId: string, createdAt: int|null, membersCount: int}>
	 */
	private function fetchRowsFromCircles(): array {
		$managerClass = 'OCA\\Circles\\CirclesManager';

		if (!class_exists($managerClass)) {
			throw new \RuntimeException('CirclesManager class not available');
		}

		$manager = Server::get($managerClass);
		if (!is_object($manager) || !method_exists($manager, 'startSuperSession') || !method_exists($manager, 'getCircles')) {
			throw new \RuntimeException('CirclesManager service not compatible');
		}

		$manager->startSuperSession();

		try {
			$circles = $manager->getCircles();
		} finally {
			if (method_exists($manager, 'stopSession')) {
				$manager->stopSession();
			}
		}

		if (!is_array($circles)) {
			return [];
		}

		$rows = [];

		foreach ($circles as $circle) {
			if (!is_object($circle) || !method_exists($circle, 'getOwner')) {
				continue;
			}

			$owner = $circle->getOwner();
			if (!is_object($owner) || !method_exists($owner, 'getUserType') || (int)$owner->getUserType() !== 1) {
				continue;
			}

			$name = '';
			if (method_exists($circle, 'getDisplayName')) {
				$name = (string)$circle->getDisplayName();
			}
			if ($name === '' && method_exists($circle, 'getName')) {
				$name = (string)$circle->getName();
			}

			$creator = '';
			if (method_exists($owner, 'getDisplayName')) {
				$creator = (string)$owner->getDisplayName();
			}

			$creatorUserId = '';
			if (method_exists($owner, 'getUserId')) {
				$creatorUserId = (string)$owner->getUserId();
			}

			if ($creator === '') {
				$creator = $creatorUserId;
			}

			$createdAt = null;
			if (method_exists($circle, 'getCreation')) {
				$creation = $circle->getCreation();
				if (is_int($creation)) {
					$createdAt = $creation;
				} elseif ($creation instanceof \DateTimeInterface) {
					$createdAt = $creation->getTimestamp();
				}
			}

			$membersCount = 0;
			if (method_exists($circle, 'getPopulation')) {
				$membersCount = max(0, (int)$circle->getPopulation());
			}

			$id = $this->extractTeamId($circle);

			$rows[] = [
				'id' => $id,
				'name' => $name,
				'creator' => $creator,
				'creatorUserId' => $creatorUserId,
				'createdAt' => $createdAt,
				'membersCount' => $membersCount,
			];
		}

		usort(
			$rows,
			static fn (array $a, array $b): int => strcmp($a['name'], $b['name'])
		);

		return $rows;
	}

	private function extractTeamId(object $circle): string {
		if (method_exists($circle, 'getSingleId')) {
			return (string)$circle->getSingleId();
		}

		if (method_exists($circle, 'getUniqueId')) {
			return (string)$circle->getUniqueId();
		}

		if (method_exists($circle, 'getId')) {
			return (string)$circle->getId();
		}

		return '';
	}

	/**
	 * @return list<array{id: string, label: string, url: string, iconSvg: string|null, iconURL: string|null, iconEmoji: string|null, provider: array{id: string, name: string, icon: string}}>
	 */
	private function fetchTeamResources(string $teamId, ?string $userId): array {
		if ($userId === null || $userId === '') {
			return [];
		}

		if (!interface_exists(ITeamManager::class)) {
			return [];
		}

		try {
			$teamManager = Server::get(ITeamManager::class);
		} catch (Throwable) {
			return [];
		}

		if (!is_object($teamManager) || !method_exists($teamManager, 'getSharedWith')) {
			return [];
		}

		try {
			$resources = $teamManager->getSharedWith($teamId, $userId);
		} catch (Throwable) {
			return [];
		}

		$mappedResources = [];
		foreach ($resources as $resource) {
			if (!$resource instanceof TeamResource) {
				continue;
			}

			$serialized = $resource->jsonSerialize();
			$provider = is_array($serialized['provider'] ?? null) ? $serialized['provider'] : [];

			$mappedResources[] = [
				'id' => (string)($serialized['id'] ?? ''),
				'label' => (string)($serialized['label'] ?? ''),
				'url' => (string)($serialized['url'] ?? ''),
				'iconSvg' => is_string($serialized['iconSvg'] ?? null) ? $serialized['iconSvg'] : null,
				'iconURL' => is_string($serialized['iconURL'] ?? null) ? $serialized['iconURL'] : null,
				'iconEmoji' => is_string($serialized['iconEmoji'] ?? null) ? $serialized['iconEmoji'] : null,
				'provider' => [
					'id' => (string)($provider['id'] ?? 'unknown'),
					'name' => (string)($provider['name'] ?? 'Unknown'),
					'icon' => (string)($provider['icon'] ?? ''),
				],
			];
		}

		usort(
			$mappedResources,
			static fn (array $a, array $b): int => strcmp(($a['provider']['name'] . ':' . $a['label']), ($b['provider']['name'] . ':' . $b['label']))
		);

		return $mappedResources;
	}

	/**
	 * @param list<array{id: string, name: string, creator: string, creatorUserId: string, createdAt: int|null, membersCount: int}> $rows
	 *
	 * @return list<array{id: string, name: string, creator: string, creatorUserId: string, createdAt: int|null, membersCount: int}>
	 */
	private function filterRows(array $rows, string $search): array {
		$term = mb_strtolower(trim($search));
		if ($term === '') {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static fn (array $row): bool => str_contains(mb_strtolower($row['name']), $term)
					|| str_contains(mb_strtolower($row['creator']), $term)
					|| str_contains(mb_strtolower($row['creatorUserId']), $term)
			)
		);
	}
}
