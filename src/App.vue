<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'

type TeamRow = {
	id: string
	name: string
	creator: string
	creatorUserId: string
	createdAt: number | null
	membersCount: number
	hasDetails: boolean
	hasTeamShare: boolean | null
}

type TeamResource = {
	id: string
	label: string
	url: string
	iconSvg: string | null
	iconURL: string | null
	iconEmoji: string | null
	provider: {
		id: string
		name: string
		icon: string
	}
}

type TeamDetails = {
	id: string
	name: string
	creator: string
	creatorUserId: string
	createdAt: number | null
	membersCount: number
	hasTeamShare: boolean
	resourcesSummary: {
		total: number
		byProvider: Record<string, number>
	}
	resources: TeamResource[]
}

type TeamDetailsState = {
	loading: boolean
	loaded: boolean
	error: string
	team: TeamDetails | null
}

type ApiPayload = {
	available: boolean
	message: string
	rows: TeamRow[]
	pagination: {
		page: number
		limit: number
		total: number
		totalPages: number
	}
}

type TeamDetailsApiPayload = {
	available: boolean
	message: string
	team: TeamDetails | null
}

const searchInput = ref('')
const loading = ref(false)
const available = ref(true)
const message = ref('')
const rows = ref<TeamRow[]>([])
const page = ref(1)
const limit = ref(25)
const total = ref(0)
const totalPages = ref(0)
const expandedTeamId = ref<string | null>(null)
const detailsByTeamId = ref<Record<string, TeamDetailsState>>({})

const hasRows = computed(() => rows.value.length > 0)

const buildApiUrl = () => {
	const webRoot = (window as Window & { OC?: { webroot?: string } }).OC?.webroot ?? ''
	const params = new URLSearchParams({
		page: String(page.value),
		limit: String(limit.value),
		search: searchInput.value,
	})

	return `${webRoot}/ocs/v2.php/apps/teamsgovernance/admin/teams?${params.toString()}`
}

const buildDetailsApiUrl = (teamId: string) => {
	const webRoot = (window as Window & { OC?: { webroot?: string } }).OC?.webroot ?? ''
	return `${webRoot}/ocs/v2.php/apps/teamsgovernance/admin/teams/${encodeURIComponent(teamId)}`
}

const formatDate = (createdAt: number | null) => {
	if (!createdAt) {
		return '—'
	}

	return new Date(createdAt * 1000).toLocaleString()
}

const fetchTeams = async () => {
	loading.value = true
	message.value = ''

	try {
		const response = await fetch(buildApiUrl(), {
			headers: {
				'Accept': 'application/json',
				'OCS-APIRequest': 'true',
			},
		})

		if (!response.ok) {
			throw new Error(`Failed to load teams (${response.status})`)
		}

		const body = await response.json() as { ocs?: { data?: ApiPayload } }
		const data = body.ocs?.data

		if (!data) {
			throw new Error('Invalid API response')
		}

		available.value = data.available
		message.value = data.message
		rows.value = data.rows
		page.value = data.pagination.page
		limit.value = data.pagination.limit
		total.value = data.pagination.total
		totalPages.value = data.pagination.totalPages
		expandedTeamId.value = null
	} catch (error) {
		available.value = false
		rows.value = []
		total.value = 0
		totalPages.value = 0
		message.value = error instanceof Error ? error.message : 'Failed to load teams.'
	} finally {
		loading.value = false
	}
}

const fetchTeamDetails = async (teamId: string) => {
	const currentState = detailsByTeamId.value[teamId]
	if (currentState?.loaded || currentState?.loading) {
		return
	}

	detailsByTeamId.value[teamId] = {
		loading: true,
		loaded: false,
		error: '',
		team: null,
	}

	try {
		const response = await fetch(buildDetailsApiUrl(teamId), {
			headers: {
				'Accept': 'application/json',
				'OCS-APIRequest': 'true',
			},
		})

		if (!response.ok) {
			throw new Error(`Failed to load details (${response.status})`)
		}

		const body = await response.json() as { ocs?: { data?: TeamDetailsApiPayload } }
		const data = body.ocs?.data

		if (!data) {
			throw new Error('Invalid details response')
		}

		detailsByTeamId.value[teamId] = {
			loading: false,
			loaded: true,
			error: data.available ? '' : (data.message || 'Details unavailable'),
			team: data.team,
		}
	} catch (error) {
		detailsByTeamId.value[teamId] = {
			loading: false,
			loaded: false,
			error: error instanceof Error ? error.message : 'Failed to load details.',
			team: null,
		}
	}
}

const toggleDetails = async (row: TeamRow) => {
	if (!row.hasDetails || row.id === '') {
		return
	}

	if (expandedTeamId.value === row.id) {
		expandedTeamId.value = null
		return
	}

	expandedTeamId.value = row.id
	await fetchTeamDetails(row.id)
}

const detailsState = (teamId: string): TeamDetailsState | null => detailsByTeamId.value[teamId] ?? null

const providerEntries = (team: TeamDetails | null): Array<[string, number]> => {
	if (!team) {
		return []
	}

	return Object.entries(team.resourcesSummary.byProvider).sort((a, b) => a[0].localeCompare(b[0]))
}

const applySearch = async () => {
	page.value = 1
	await fetchTeams()
}

const goToPreviousPage = async () => {
	if (page.value <= 1 || loading.value) {
		return
	}

	page.value -= 1
	await fetchTeams()
}

const goToNextPage = async () => {
	if (loading.value || (totalPages.value > 0 && page.value >= totalPages.value)) {
		return
	}

	page.value += 1
	await fetchTeams()
}

onMounted(async () => {
	await fetchTeams()
})
</script>

<template>
	<div :class="$style.content">
			<div :class="$style.header">
				<h2 :class="$style.title">
					Teams governance
				</h2>
				<p :class="$style.subtitle">
					All user-created Teams with creator, creation date and member count.
				</p>
			</div>

			<div :class="$style.controls">
				<NcTextField
					v-model="searchInput"
					label="Search"
					placeholder="Search by team, creator display name or username"
					@keyup.enter="applySearch" />
				<NcButton type="primary" @click="applySearch">
					Search
				</NcButton>
			</div>

			<div v-if="loading" :class="$style.loadingWrap">
				<NcLoadingIcon :size="24" />
				<span>Loading teams…</span>
			</div>

			<NcEmptyContent
				v-else-if="!available"
				:name="message || 'Teams data unavailable'"
				description="Make sure the Teams app is enabled." />

			<NcEmptyContent
				v-else-if="!hasRows"
				name="No teams found"
				description="Try adjusting your search query." />

			<div v-else :class="$style.tableWrap">
				<table :class="$style.table">
					<thead>
						<tr>
							<th :class="$style.headerCell">Team</th>
							<th :class="$style.headerCell">Creator</th>
							<th :class="$style.headerCell">Created</th>
							<th :class="$style.headerCell">Members</th>
							<th :class="$style.headerCell">Details</th>
						</tr>
					</thead>
					<tbody>
						<template v-for="(row, index) in rows" :key="row.id || `${row.name}-${index}`">
							<tr>
								<td>{{ row.name || '—' }}</td>
								<td>{{ row.creator || '—' }}</td>
								<td>{{ formatDate(row.createdAt) }}</td>
								<td>{{ row.membersCount }}</td>
								<td>
									<NcButton :disabled="!row.hasDetails" @click="toggleDetails(row)">
										{{ expandedTeamId === row.id ? 'Hide details' : 'View details' }}
									</NcButton>
								</td>
							</tr>
							<tr v-if="row.id !== '' && expandedTeamId === row.id">
								<td :colspan="5" :class="$style.detailsCell">
									<div v-if="detailsState(row.id)?.loading" :class="$style.detailsLoading">
										<NcLoadingIcon :size="20" />
										<span>Loading details…</span>
									</div>
									<div v-else-if="detailsState(row.id)?.error" :class="$style.detailsError">
										{{ detailsState(row.id)?.error }}
									</div>
									<div v-else-if="detailsState(row.id)?.team" :class="$style.detailsContent">
										<div :class="$style.detailsSummary">
											<div><strong>Team share (Files):</strong> {{ detailsState(row.id)?.team?.hasTeamShare ? 'Yes' : 'No' }}</div>
											<div><strong>Connected resources:</strong> {{ detailsState(row.id)?.team?.resourcesSummary.total ?? 0 }}</div>
										</div>

										<div v-if="providerEntries(detailsState(row.id)?.team ?? null).length > 0" :class="$style.providerCounts">
											<span v-for="entry in providerEntries(detailsState(row.id)?.team ?? null)" :key="entry[0]" :class="$style.providerChip">
												{{ entry[0] }}: {{ entry[1] }}
											</span>
										</div>

										<ul v-if="(detailsState(row.id)?.team?.resources.length ?? 0) > 0" :class="$style.resourceList">
											<li
												v-for="resource in detailsState(row.id)?.team?.resources ?? []"
												:key="`${resource.provider.id}-${resource.id}`"
												:class="$style.resourceItem">
												<span :class="$style.resourceProvider">{{ resource.provider.name }}</span>
												<a v-if="resource.url" :href="resource.url" target="_blank" rel="noopener noreferrer">{{ resource.label || resource.id }}</a>
												<span v-else>{{ resource.label || resource.id }}</span>
											</li>
										</ul>
										<div v-else :class="$style.detailsMuted">
											No connected resources found for this team.
										</div>
									</div>
								</td>
							</tr>
						</template>
					</tbody>
				</table>

				<div :class="$style.pagination">
					<div>
						Page {{ page }}<span v-if="totalPages > 0"> / {{ totalPages }}</span>
					</div>
					<div>
						Total teams: {{ total }}
					</div>
					<div :class="$style.pageButtons">
						<NcButton :disabled="page <= 1 || loading" @click="goToPreviousPage">
							Previous
						</NcButton>
						<NcButton :disabled="(totalPages > 0 && page >= totalPages) || loading" @click="goToNextPage">
							Next
						</NcButton>
					</div>
				</div>
			</div>
	</div>
</template>

<style module>
.content {
	display: flex;
	flex-direction: column;
	gap: 16px;
	max-width: 100%;
	padding: 28px 16px 16px;
	box-sizing: border-box;
}

.header {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.title {
	margin: 0;
}

.subtitle {
	margin: 0;
}

.controls {
	display: grid;
	gap: 12px;
	grid-template-columns: 1fr auto;
	align-items: end;
}

.loadingWrap {
	display: flex;
	align-items: center;
	gap: 8px;
}

.tableWrap {
	display: flex;
	flex-direction: column;
	gap: 12px;
	overflow-x: auto;
}

.table {
	width: 100%;
	min-width: 760px;
	border-collapse: collapse;
}

.table th,
.table td {
	text-align: left;
	padding: 10px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: top;
}

.headerCell {
	font-weight: 700;
}

.detailsCell {
	background: var(--color-background-dark);
}

.detailsLoading {
	display: flex;
	align-items: center;
	gap: 8px;
}

.detailsError {
	color: var(--color-error);
}

.detailsContent {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.detailsSummary {
	display: flex;
	gap: 20px;
	flex-wrap: wrap;
}

.providerCounts {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.providerChip {
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid var(--color-border);
	font-size: 12px;
}

.resourceList {
	margin: 0;
	padding-left: 18px;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.resourceItem {
	display: flex;
	gap: 8px;
	align-items: baseline;
	flex-wrap: wrap;
}

.resourceProvider {
	font-weight: 700;
}

.detailsMuted {
	opacity: 0.8;
}

.pagination {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.pageButtons {
	display: flex;
	gap: 8px;
}

@media (max-width: 768px) {
	.controls {
		grid-template-columns: 1fr;
	}

	.pagination {
		flex-direction: column;
		align-items: flex-start;
	}
}
</style>
