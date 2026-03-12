<?php

namespace App\Http\Controllers;

use App\Services\DesignTestRunnerService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Design Guard PART 8 — Visual Test Generation.
 *
 * GET /design-tests runs design quality tests for example verticals (fashion, electronics,
 * cosmetics, pet, furniture), shows scores and issues. When score < threshold, issues are logged.
 */
class DesignTestsController extends Controller
{
    public function __construct(
        protected DesignTestRunnerService $runner
    ) {}

    /**
     * Run design tests and show results (generated sites + scores).
     */
    public function index(): Response
    {
        $data = $this->runner->run();

        return Inertia::render('DesignTests/Index', [
            'threshold' => $data['threshold'],
            'results' => $data['results'],
        ]);
    }
}
