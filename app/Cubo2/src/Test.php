<?php 
/**
 * @package Cubo
 * @author mateus - github.com/eeomts
 * 04/06/26 14:29
 * 
 * readonly class Test, usada como objeto para criar novos testes em benchmark.php
 * 
 */
namespace Cubo;

readonly class Test
{
    /**
     * @param int $code
     * @param string $identifier - indentificador do teste
     * @param string $desc - descricao do teste
     * @param float $initTime
     * @param null|float $endTime 
     */
    public function __construct(
        public int $code,
        public string $identifier,
        public string $desc,
        public float $initTime,
        public ?float $endTime = null
    ) {}

    /**
     * @return static - Test(code, identifier, desc, initTime, endTime)
     */
    final public function finishTest(): static
    {
        return new static($this->code, $this->identifier, $this->desc, $this->initTime, microtime(true));
    }

    /**
     * @return float - abs(initTime - endTime) -> sempre positivo
     */
    final public function getTime(): float {

        if($this->endTime === null){
            throw new \RuntimeException("O teste: " . $this->desc . " ainda nao foi finalizado");
        }
        
        return abs($this->initTime - $this->endTime);
    }
}
