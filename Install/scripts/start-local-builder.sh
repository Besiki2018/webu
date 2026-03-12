#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PREBUILT_DIR="$(cd "${INSTALL_DIR}/../Builder/prebuilt" && pwd)"

append_path_if_dir() {
    local dir="$1"
    if [[ -d "${dir}" ]]; then
        PATH="${dir}:${PATH}"
    fi
}

# Builder child პროცესები (build/npm) ეყრდნობა PATH-ს.
# web server-დან გაშვებისას PATH ხშირად მოკლეა, ამიტომ ვაფართოებთ ცნობილ ბინარ-დირექტორიებზე.
append_path_if_dir "/opt/homebrew/bin"
append_path_if_dir "/usr/local/bin"
append_path_if_dir "/usr/bin"
append_path_if_dir "/bin"
append_path_if_dir "/usr/sbin"
append_path_if_dir "/sbin"
append_path_if_dir "${INSTALL_DIR}/node_modules/.bin"

# თუ npm ჯერაც არ მოიძებნა, ვცდილობთ nvm/bin ბილიკების ავტო-დამატებას.
if ! command -v npm >/dev/null 2>&1; then
    OWNER_USER="$(stat -f '%Su' "${INSTALL_DIR}" 2>/dev/null || true)"
    OWNER_HOME=""
    if [[ -n "${OWNER_USER}" ]]; then
        OWNER_HOME="$(eval echo "~${OWNER_USER}" 2>/dev/null || true)"
    fi

    NVM_CANDIDATES=(
        "${NVM_DIR:-}"
        "${HOME:-}/.nvm"
        "${OWNER_HOME}/.nvm"
    )

    for nvm_dir in "${NVM_CANDIDATES[@]}"; do
        if [[ -n "${nvm_dir}" && -s "${nvm_dir}/nvm.sh" ]]; then
            # shellcheck source=/dev/null
            . "${nvm_dir}/nvm.sh" >/dev/null 2>&1 || true
            nvm use --silent default >/dev/null 2>&1 || true
        fi
    done

    for node_bin in \
        "${HOME:-}"/.nvm/versions/node/*/bin \
        "${OWNER_HOME}"/.nvm/versions/node/*/bin; do
        if [[ -d "${node_bin}" ]]; then
            PATH="${node_bin}:${PATH}"
        fi
    done
fi

export PATH

OS="$(uname -s)"
ARCH="$(uname -m)"

case "${OS}" in
  Darwin)
    BINARY_PATH="${PREBUILT_DIR}/webby-builder-macos"
    ;;
  Linux)
    if [[ "${ARCH}" == "aarch64" || "${ARCH}" == "arm64" ]]; then
      BINARY_PATH="${PREBUILT_DIR}/webby-builder-arm64"
    else
      BINARY_PATH="${PREBUILT_DIR}/webby-builder-linux"
    fi
    ;;
  *)
    echo "Unsupported OS for local builder: ${OS}" >&2
    exit 1
    ;;
esac

if [[ ! -f "${BINARY_PATH}" ]]; then
    echo "Local builder binary not found: ${BINARY_PATH}" >&2
    exit 1
fi

chmod +x "${BINARY_PATH}"

# macOS Gatekeeper ხშირად ბლოკავს prebuilt binary-ს. ვცდილობთ quarantine-ის მოხსნას.
if [[ "${OS}" == "Darwin" ]]; then
    xattr -d com.apple.quarantine "${BINARY_PATH}" >/dev/null 2>&1 || true
fi

BUILDER_PORT="${BUILDER_PORT:-8846}"
BUILDER_SERVER_KEY="${BUILDER_SERVER_KEY:-123456}"

if command -v npm >/dev/null 2>&1; then
    echo "Detected npm: $(command -v npm)"
else
    echo "Warning: npm not found in PATH. Build endpoint may fail until Node.js is available." >&2
fi

echo "Starting local builder: ${BINARY_PATH} (port=${BUILDER_PORT})"
exec "${BINARY_PATH}" --port "${BUILDER_PORT}" --key "${BUILDER_SERVER_KEY}"
