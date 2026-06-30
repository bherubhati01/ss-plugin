<?php
/**
 * GitHub Auto-Updater for Social Auto Scheduler
 *
 * Uses Plugin Update Checker (by Yahnis Elsts) to pull automatic updates
 * from GitHub Releases.  WordPress compares the installed plugin's Version
 * header against the latest GitHub Release tag and shows an "Update
 * Available" notice with one-click update support.
 *
 * Library: https://github.com/YahnisElsts/plugin-update-checker
 *
 * HOW TO ACTIVATE
 * ───────────────
 * Install the library into plugin-update-checker/ (see instructions at the
 * bottom of this file), then replace YOUR_GITHUB_USERNAME and
 * YOUR_REPOSITORY_NAME below with your real values.
 *
 * @package SocialAutoScheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── 1. Locate the Plugin Update Checker library ────────────────────────────
//
// We check two locations so the code works whether the library was installed
// manually (recommended) or via Composer.
//
//  • Manual / git-submodule → plugin-update-checker/
//  • Composer               → vendor/yahnis-elsts/plugin-update-checker/
$sas_puc_candidates = array(
	SAS_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php',
	SAS_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php',
);

$sas_puc_loaded = false;

foreach ( $sas_puc_candidates as $sas_puc_file ) {
	if ( file_exists( $sas_puc_file ) ) {
		require_once $sas_puc_file;
		$sas_puc_loaded = true;
		break;
	}
}

if ( ! $sas_puc_loaded ) {
	// Library not installed — skip silently so the plugin keeps working.
	// Enable WP_DEBUG to see the notice below.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error(
			'Social Auto Scheduler: Plugin Update Checker library not found. ' .
			'Install it with: git submodule add https://github.com/YahnisElsts/plugin-update-checker.git plugin-update-checker',
			E_USER_NOTICE
		);
	}
	return;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// ── 2. Build the update checker ────────────────────────────────────────────
//
// Replace the GitHub URL placeholders with your actual repository URL.
// The URL must end with a trailing slash.
//
// Example: 'https://github.com/johndoe/social-auto-scheduler/'
$sas_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/bherubhati01/ss-plugin/',
	SAS_PLUGIN_DIR . 'social-auto-scheduler.php',                    // Main plugin file.
	'social-auto-scheduler'                                           // Plugin slug (= folder name).
);

// ── 3. Track the stable branch ─────────────────────────────────────────────
//
// PUC will look at GitHub Releases on this branch.
$sas_update_checker->setBranch( 'main' );

// ── 4. Use GitHub Release assets as the update ZIP ─────────────────────────
//
// When you publish a GitHub Release, attach the plugin ZIP as a release
// asset (any .zip filename works; naming it "social-auto-scheduler.zip"
// is conventional).  PUC downloads that asset for one-click updates.
//
// If no asset is attached PUC falls back to the source ZIP GitHub
// generates automatically from the tag — which also works, but may
// contain extra development files.
$sas_update_checker->getVcsApi()->enableReleaseAssets();

// ── 5. Private repository support (optional) ───────────────────────────────
//
// Skip this block entirely if your repository is public.
//
// Steps for a private repository:
//   a) Create a Personal Access Token at https://github.com/settings/tokens
//      Required scope: "repo" (for classic tokens) or "Contents: read"
//      (for fine-grained tokens).
//   b) Add the constant to wp-config.php — NEVER commit a real token:
//        define( 'SAS_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx' );
//   c) Uncomment the block below:
//
// if ( defined( 'SAS_GITHUB_TOKEN' ) && SAS_GITHUB_TOKEN ) {
// 	$sas_update_checker->setAuthentication( SAS_GITHUB_TOKEN );
// }

// ── Done ───────────────────────────────────────────────────────────────────
//
// LIBRARY INSTALLATION INSTRUCTIONS
// ───────────────────────────────────
//
// Option A — Git submodule (recommended; keeps the library updatable):
//
//   git submodule add https://github.com/YahnisElsts/plugin-update-checker.git \
//       plugin-update-checker
//   git submodule update --init --recursive
//   git add .gitmodules plugin-update-checker
//   git commit -m "Add Plugin Update Checker as a submodule"
//
// Option B — Direct copy (no git history link):
//
//   git clone --depth=1 https://github.com/YahnisElsts/plugin-update-checker.git \
//       plugin-update-checker
//   rm -rf plugin-update-checker/.git
//   git add plugin-update-checker
//   git commit -m "Add Plugin Update Checker library"
//
// Option C — Composer:
//
//   composer require yahnis-elsts/plugin-update-checker
//   # Library lands in vendor/yahnis-elsts/plugin-update-checker/
//   # This file already checks that path as a fallback.
//
// CREATING A GITHUB RELEASE
// ──────────────────────────
//   1. Bump the Version in social-auto-scheduler.php header, e.g. 1.0.1
//   2. Commit and push:
//        git add social-auto-scheduler.php
//        git commit -m "Release v1.0.1"
//        git push origin main
//   3. Create a release tag:
//        git tag v1.0.1
//        git push origin v1.0.1
//   4. On GitHub → Releases → Draft a new release → choose tag v1.0.1
//   5. Attach the plugin ZIP as a release asset (optional but recommended):
//        zip -r social-auto-scheduler.zip social-auto-scheduler/ \
//            --exclude "*/node_modules/*" --exclude "*/.git/*"
//   6. Publish the release.
//   WordPress will detect the update within 12 hours, or immediately
//   after clicking "Check for updates" on the Plugins page.
