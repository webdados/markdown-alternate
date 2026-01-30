<?php
/**
 * Core plugin orchestrator.
 *
 * @package MarkdownAlternate
 */

namespace MarkdownAlternate;

use MarkdownAlternate\Router\RewriteHandler;

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
     * Router instance.
     *
     * @var RewriteHandler
     */
    private $router;

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
        $this->router = new RewriteHandler();
        $this->router->register();
    }

    /**
     * Plugin activation callback.
     *
     * Registers rewrite rules and flushes to persist them.
     *
     * @return void
     */
    public static function activate(): void {
        RewriteHandler::register_rules();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation callback.
     *
     * Flushes rewrite rules to remove .md URL endpoints.
     *
     * @return void
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
