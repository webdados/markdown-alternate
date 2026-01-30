<?php
/**
 * Core plugin orchestrator.
 *
 * @package MarkdownAlternate
 */

namespace MarkdownAlternate;

/**
 * Main plugin class implementing singleton pattern.
 */
class Plugin {

    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton.
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Register activation and deactivation hooks.
     *
     * @return void
     */
    private function register_hooks(): void {
        register_activation_hook(MARKDOWN_ALTERNATE_FILE, [$this, 'activate']);
        register_deactivation_hook(MARKDOWN_ALTERNATE_FILE, [$this, 'deactivate']);
    }

    /**
     * Plugin activation callback.
     *
     * Flushes rewrite rules to register .md URL endpoints.
     *
     * @return void
     */
    public function activate(): void {
        // Register rewrite rules before flushing (added in Plan 02)
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation callback.
     *
     * Flushes rewrite rules to remove .md URL endpoints.
     *
     * @return void
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Initialize the router.
     *
     * Placeholder for URL routing initialization (added in Plan 02).
     *
     * @return void
     */
    public function init_router(): void {
        // Router initialization will be added in Plan 02
    }
}
