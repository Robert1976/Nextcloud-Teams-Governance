#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

SKIP_BUILD=false
OUTPUT_DIR="${APP_ROOT}/dist"

usage() {
	cat <<'EOF'
Create a deployable prebuilt tarball for Nextcloud custom_apps.

Usage:
  scripts/create-release-tar.sh [--skip-build] [--output-dir <path>]

Options:
  --skip-build          Do not run npm build before packaging.
  --output-dir <path>   Output directory for the generated tarball.
EOF
}

while (($#)); do
	case "$1" in
		--skip-build)
			SKIP_BUILD=true
			shift
			;;
		--output-dir)
			if (($# < 2)); then
				echo "Missing value for --output-dir" >&2
				exit 1
			fi
			OUTPUT_DIR="$2"
			shift 2
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			echo "Unknown argument: $1" >&2
			usage
			exit 1
			;;
	esac
done

if [[ ! -f "${APP_ROOT}/appinfo/info.xml" ]]; then
	echo "Could not find appinfo/info.xml in ${APP_ROOT}" >&2
	exit 1
fi

APP_ID="$(sed -n 's:.*<id>\(.*\)</id>.*:\1:p' "${APP_ROOT}/appinfo/info.xml" | head -n1)"
APP_VERSION="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "${APP_ROOT}/appinfo/info.xml" | head -n1)"

if [[ -z "${APP_ID}" || -z "${APP_VERSION}" ]]; then
	echo "Could not parse app id/version from appinfo/info.xml" >&2
	exit 1
fi

if [[ "${SKIP_BUILD}" != true ]]; then
	echo "Building frontend assets..."
	(
		cd "${APP_ROOT}"
		npm run build
	)
fi

for required in appinfo lib templates img js css; do
	if [[ ! -e "${APP_ROOT}/${required}" ]]; then
		echo "Required path missing: ${required}" >&2
		exit 1
	fi
done

mkdir -p "${OUTPUT_DIR}"
STAGE_DIR="$(mktemp -d)"
PACKAGE_ROOT="${STAGE_DIR}/${APP_ID}"
mkdir -p "${PACKAGE_ROOT}"

INCLUDE_PATHS=(
	"appinfo"
	"lib"
	"templates"
	"img"
	"js"
	"css"
	"README.md"
	"CHANGELOG.md"
	"LICENSE"
	"composer.json"
)

for rel_path in "${INCLUDE_PATHS[@]}"; do
	src="${APP_ROOT}/${rel_path}"
	[[ -e "${src}" ]] || continue

	dest="${PACKAGE_ROOT}/${rel_path}"
	if [[ -d "${src}" ]]; then
		mkdir -p "${dest}"
		cp -a "${src}/." "${dest}/"
	else
		mkdir -p "$(dirname "${dest}")"
		cp -a "${src}" "${dest}"
	fi
done

find "${PACKAGE_ROOT}" -type f -name '*:Zone.Identifier' -delete
find "${PACKAGE_ROOT}" -maxdepth 1 -type f -name 'tmpclaude-*-cwd' -delete
find "${PACKAGE_ROOT}" -maxdepth 1 -type f -name '.tmp' -delete

OUTPUT_FILE="${APP_ID}-${APP_VERSION}-prebuilt.tar.gz"
OUTPUT_PATH="$(cd "${OUTPUT_DIR}" && pwd)/${OUTPUT_FILE}"

tar -C "${STAGE_DIR}" -czf "${OUTPUT_PATH}" "${APP_ID}"
rm -rf "${STAGE_DIR}"

echo "Created package: ${OUTPUT_PATH}"