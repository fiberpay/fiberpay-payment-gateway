#!/usr/bin/env bash
set -euo pipefail

ORIGINAL_ENTRYPOINT="/usr/local/bin/docker-entrypoint.sh"

ENSURE_INSTALLED="/usr/local/bin/docker-ensure-installed.sh"

if [ ! -x "$ORIGINAL_ENTRYPOINT" ]; then
	echo "Missing original WordPress entrypoint at $ORIGINAL_ENTRYPOINT" >&2
	exit 1
fi

WP_PATH="${WP_PATH:-/var/www/html}"

cd "$WP_PATH"

# 1) Ensure WordPress files and wp-config.php exist.
#    The official entrypoint only does the copy/config work for apache2*/php-fpm
#    or when invoked as docker-ensure-installed.sh (basename check). We use a symlink
#    so we can trigger the logic without actually starting Apache.
if [ ! -e "$ENSURE_INSTALLED" ]; then
	ln -s "$ORIGINAL_ENTRYPOINT" "$ENSURE_INSTALLED"
fi

"$ENSURE_INSTALLED" true >/dev/null

AUTO_SETUP="${FIBERPAYGW_AUTO_SETUP:-1}"

if [ "$AUTO_SETUP" != "1" ]; then
	exec "$ORIGINAL_ENTRYPOINT" "$@"
fi

WP_URL="${WP_URL:-http://localhost:8080}"
WP_TITLE="${WP_TITLE:-Fiberpay Dev}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-admin123}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

SETUP_MARKER="${FIBERPAYGW_SETUP_MARKER:-$WP_PATH/.fiberpaygw-setup-complete}"

# wp-cli should be present (installed in Dockerfile). If not, fail fast.
if ! command -v wp >/dev/null 2>&1; then
	echo "wp-cli not found in PATH" >&2
	exit 1
fi

fiberpaygw_auto_setup() {
	set -euo pipefail

	if [ -f "$SETUP_MARKER" ]; then
		echo "FiberpayGW auto-setup: already completed"
		return 0
	fi

	wp_cmd=(wp --path="$WP_PATH" --allow-root --url="$WP_URL")

	echo "FiberpayGW auto-setup: waiting for DB..."
	db_host="${WORDPRESS_DB_HOST:-db}"
	db_user="${WORDPRESS_DB_USER:-}"
	db_pass="${WORDPRESS_DB_PASSWORD:-}"
	db_name="${WORDPRESS_DB_NAME:-}"
	for i in $(seq 1 60); do
		DB_HOST="$db_host" DB_USER="$db_user" DB_PASS="$db_pass" DB_NAME="$db_name" \
			php -r 'mysqli_report(MYSQLI_REPORT_OFF); $m=@mysqli_connect(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASS"), getenv("DB_NAME")); if ($m) { mysqli_close($m); exit(0); } exit(1);' \
			>/dev/null 2>&1 \
			&& break
		sleep 1
	done

	echo "FiberpayGW auto-setup: ensuring WP core installed..."
	if ! "${wp_cmd[@]}" core is-installed >/dev/null 2>&1; then
		"${wp_cmd[@]}" core install \
			--title="$WP_TITLE" \
			--admin_user="$WP_ADMIN_USER" \
			--admin_password="$WP_ADMIN_PASSWORD" \
			--admin_email="$WP_ADMIN_EMAIL" \
			--skip-email
	fi

	echo "FiberpayGW auto-setup: ensuring WooCommerce installed + active..."
	if ! "${wp_cmd[@]}" plugin is-installed woocommerce >/dev/null 2>&1; then
		"${wp_cmd[@]}" plugin install woocommerce --activate
	else
		"${wp_cmd[@]}" plugin activate woocommerce >/dev/null 2>&1 || true
	fi

	echo "FiberpayGW auto-setup: activating Fiberpay plugin..."
	"${wp_cmd[@]}" plugin activate fiberpay-payment-gateway >/dev/null 2>&1 || true

	date -u +'%Y-%m-%dT%H:%M:%SZ' > "$SETUP_MARKER" || true
	echo "FiberpayGW auto-setup: done"
}

if [ "$AUTO_SETUP" = "1" ]; then
	fiberpaygw_auto_setup &
fi

exec "$ORIGINAL_ENTRYPOINT" "$@"
