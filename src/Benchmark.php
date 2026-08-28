<?php

/**
 * Ferramenta de verificação de performance.
 *
 * @package Cubo
 * @author v1: Cristiano
 * @author v2: Mateus - github.com/eeomts
 */

namespace Cubo;

class Benchmark
{
    /**
     * @return self
     * @return null
     */
    private static ?self $instance = null;

    /** @var array<string, Test> */
    private array $tests = [];

    private function __construct() {}

    /**
     * @return static
     */
    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * @param string $identify = test name
     */
    public function initializeTest(string $identify, string $desc = ''): void
    {
        if (isset($this->tests[$identify])) {
            throw new \RuntimeException("Benchmark: '{$identify}' já foi iniciado.");
        }

        $this->tests[$identify] = new Test(
            code: count($this->tests) + 1,
            identifier: $identify,
            desc: $desc,
            initTime: microtime(true),
        );
    }

    /**
     * @param string $identify = initializeTest(@param string $identify) 
     */
    public function endTest(string $identify): void
    {
        if (!isset($this->tests[$identify])) {
            throw new \RuntimeException("Benchmark: '{$identify}' não existe.");
        }

        #belissimo codigo deixado para recordacao#
        // if (!is_array($test))
        // die("seu bosta, o teste: " . $identify . " não existe.");

        $this->tests[$identify] = $this->tests[$identify]->finishTest();
    }

    /**
     * @param string $identify - identificador do teste
     * @return array [
     *  code, -- codigo do teste
     *  identifier, -- identificador do teste
     *  desc, -- descricao do teste
     *  time -- tempo total gasto no teste
     * ]
     */
    public function getResult(string $identify): array
    {
        if (!isset($this->tests[$identify]))
            throw new \RuntimeException("Benchmark: '{$identify}' não existe.");

        $test = $this->tests[$identify];

        return [
            'code' => $test->code,
            'identifier' => $test->identifier,
            'desc' => $test->desc,
            'time' => round($test->getTime(), 4),
        ];
    }
}
