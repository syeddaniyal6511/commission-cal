<?php

namespace App\Services;

use App\Models\Formula;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class FormulaService
{
    private const BASE_VARIABLES = ['AnnualUsage', 'ContractValue', 'ContractLength', 'RiskScore'];

    public function index(): Collection
    {
        return Formula::with('dependentVariables')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();
    }

    public function activate(Formula $formula): Formula
    {
        return DB::transaction(function () use ($formula) {
            Formula::query()->update(['is_active' => false]);
            $formula->update(['is_active' => true]);
            return $formula->load('dependentVariables');
        });
    }

    public function store(array $data): Formula
    {
        $this->validateExpressions($data);

        return DB::transaction(function () use ($data) {
            $formula = Formula::create([
                'version'    => $data['version'],
                'expression' => $data['expression'],
                'is_active'  => false,
            ]);

            foreach ($data['variables'] ?? [] as $index => $var) {
                $formula->dependentVariables()->create([
                    'name'            => $var['name'],
                    'expression'      => $var['expression'],
                    'execution_order' => $var['execution_order'] ?? $index,
                ]);
            }

            return $formula->load('dependentVariables');
        });
    }

    private function validateExpressions(array $data): void
    {
        $el = new ExpressionLanguage();

        $variables   = collect($data['variables'] ?? [])->sortBy('execution_order')->values()->all();
        $subVarNames = array_column($variables, 'name');
        $allNames    = array_merge(self::BASE_VARIABLES, $subVarNames);

        // ── Sort order: each var can only use names resolved before it ────────
        $resolved = array_flip(self::BASE_VARIABLES);

        foreach ($variables as $var) {
            $name = $var['name'];

            preg_match_all('/\b([A-Z][a-zA-Z0-9]*)\b/', $var['expression'] ?? '', $matches);

            foreach (array_unique($matches[1]) as $usedName) {
                if (!isset($resolved[$usedName])) {
                    throw ValidationException::withMessages([
                        'variables' => [
                            "Sub-variable [{$name}] references [{$usedName}] which is not yet defined at execution_order {$var['execution_order']}.",
                        ],
                    ]);
                }
            }

            $resolved[$name] = true;
        }

        // ── Circular reference detection (DFS) ────────────────────────────────
        $deps = [];
        foreach ($variables as $var) {
            $name = $var['name'];
            preg_match_all('/\b([A-Z][a-zA-Z0-9]*)\b/', $var['expression'] ?? '', $m);
            $deps[$name] = array_filter(
                array_unique($m[1]),
                fn ($n) => $n !== $name && in_array($n, $subVarNames)
            );
        }

        if ($this->hasCircularDependency($deps)) {
            throw ValidationException::withMessages([
                'variables' => ['Circular reference detected between sub-variables.'],
            ]);
        }

        // ── ExpressionLanguage::parse() — syntax + name check ─────────────────
        foreach ($variables as $var) {
            $availableNames = array_merge(
                self::BASE_VARIABLES,
                array_filter($subVarNames, fn ($n) => $n !== $var['name'])
            );

            try {
                $el->parse($var['expression'], array_values($availableNames));
            } catch (SyntaxError $e) {
                throw ValidationException::withMessages([
                    'variables' => ["Sub-variable [{$var['name']}]: " . $e->getMessage()],
                ]);
            }
        }

        try {
            $el->parse($data['expression'], $allNames);
        } catch (SyntaxError $e) {
            throw ValidationException::withMessages([
                'expression' => ['Main expression: ' . $e->getMessage()],
            ]);
        }
    }

    private function hasCircularDependency(array $deps): bool
    {
        $visited = [];
        $stack   = [];

        $hasCycle = function (string $node) use (&$hasCycle, &$visited, &$stack, $deps): bool {
            $visited[$node] = true;
            $stack[$node]   = true;

            foreach ($deps[$node] ?? [] as $dep) {
                if (!isset($visited[$dep]) ? $hasCycle($dep) : isset($stack[$dep])) {
                    return true;
                }
            }

            unset($stack[$node]);
            return false;
        };

        foreach (array_keys($deps) as $node) {
            if (!isset($visited[$node]) && $hasCycle($node)) {
                return true;
            }
        }

        return false;
    }
}
