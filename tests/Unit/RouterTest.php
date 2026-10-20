<?php

declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

/**
 * Tests for the Router component.
 * 
 * Validates route registration, dispatch, dynamic parameters,
 * and 404 handling.
 */
final class RouterTest extends DatabaseTestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }

    // ──────────────────────────────────────────
    // Route registration
    // ──────────────────────────────────────────

    public function testCanRegisterGetRoute(): void
    {
        // Router stores routes privately; we verify by dispatching
        // Use a simple callable class for testing
        $this->router->get('/test', [RouterTestController::class, 'index']);

        ob_start();
        $this->router->dispatch('GET', '/test');
        $output = ob_get_clean();

        $this->assertSame('index_called', $output);
    }

    public function testCanRegisterPostRoute(): void
    {
        $this->router->post('/submit', [RouterTestController::class, 'store']);

        ob_start();
        $this->router->dispatch('POST', '/submit');
        $output = ob_get_clean();

        $this->assertSame('store_called', $output);
    }

    // ──────────────────────────────────────────
    // Dynamic parameters
    // ──────────────────────────────────────────

    public function testDispatchesDynamicRouteWithId(): void
    {
        $this->router->get('/users/{id}', [RouterTestController::class, 'show']);

        ob_start();
        $this->router->dispatch('GET', '/users/42');
        $output = ob_get_clean();

        $this->assertSame('show_42', $output);
    }

    public function testDynamicRouteOnlyMatchesNumericIds(): void
    {
        $this->router->get('/users/{id}', [RouterTestController::class, 'show']);

        ob_start();
        $this->router->dispatch('GET', '/users/abc');
        $output = ob_get_clean();

        // The regex pattern expects [0-9]+, so "abc" should 404
        $this->assertStringContainsString('404', $output);
    }

    // ──────────────────────────────────────────
    // 404 handling
    // ──────────────────────────────────────────

    public function testReturns404ForUnknownRoute(): void
    {
        ob_start();
        $this->router->dispatch('GET', '/nonexistent');
        $output = ob_get_clean();

        $this->assertStringContainsString('404', $output);
    }

    public function testReturns404ForWrongMethod(): void
    {
        $this->router->get('/only-get', [RouterTestController::class, 'index']);

        ob_start();
        $this->router->dispatch('POST', '/only-get');
        $output = ob_get_clean();

        $this->assertStringContainsString('404', $output);
    }

    // ──────────────────────────────────────────
    // Route isolation
    // ──────────────────────────────────────────

    public function testGetAndPostSameUriAreSeparate(): void
    {
        $this->router->get('/dual', [RouterTestController::class, 'index']);
        $this->router->post('/dual', [RouterTestController::class, 'store']);

        ob_start();
        $this->router->dispatch('GET', '/dual');
        $getOutput = ob_get_clean();

        ob_start();
        $this->router->dispatch('POST', '/dual');
        $postOutput = ob_get_clean();

        $this->assertSame('index_called', $getOutput);
        $this->assertSame('store_called', $postOutput);
    }
}

/**
 * Minimal controller for Router testing.
 */
class RouterTestController
{
    public function index(): void
    {
        echo 'index_called';
    }

    public function store(): void
    {
        echo 'store_called';
    }

    public function show($id): void
    {
        echo "show_$id";
    }
}
