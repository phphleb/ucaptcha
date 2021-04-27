<?php


namespace Phphleb\Ucaptcha;


class Base implements CaptchaInterface
{
    const BASE_TYPE = 'base';

    const LIGHT_TYPE = 'light';

    const DARK_TYPE = 'light';

    const VARIABLE_LEVEL = 0;

    const FIRST_LEVEL = 1;

    const SECOND_LEVEL = 2;

    const TYPES = [self::BASE_TYPE, self::LIGHT_TYPE, self::DARK_TYPE];

    const LEVELS = [self::VARIABLE_LEVEL, self::FIRST_LEVEL, self::SECOND_LEVEL];

    private $actualType = self::BASE_TYPE;

    private $actualLevel = self::VARIABLE_LEVEL;

    private $secretCode = '';

    private $secretCodeV2 = '';

    private $codeLength;

    private $symbols = 'abcdefghijkmnpqrstuvwxyABCDEFGHJKLMNPQRSTUVWXYZ2346789';

    private $similarSymbols = ['c', 'i', 's', 'u', 'v', 'w', 'x', 'p'];

    private $similarSymbolsV2 = ['C', 'I', 'S', 'U', 'V', 'W', 'X', 'P'];

    public function __construct()
    {
        $this->codeLength = rand(5, 7);
        $this->createCode();
    }

    /**
     * @inheritDoc
     */
    function getType(): string
    {
        return $this->actualType;
    }

    /**
     * @inheritDoc
     */
    function setType(string $type)
    {
        if (!in_array($type, self::TYPES)) {
            return;
        }

        $this->actualType = $type;
    }

    /**
     * @inheritDoc
     */
    function getAttentionLevel(): int
    {
        return $this->actualLevel;
    }

    /**
     * @inheritDoc
     */
    function setAttentionLevel(int $level)
    {
        if (!in_array($level, self::LEVELS)) {
            return;
        }

        $this->actualType = $level;
    }

    /**
     * @inheritDoc
     */
    function getImageRawData()
    {
        // TODO: Implement getImageRawData() method.
    }

    /**
     * @inheritDoc
     */
    function getSecretCode(): array
    {
        return [$this->secretCode, $this->secretCodeV2];
    }

    protected function createCode()
    {
        if (!empty($this->secretCode)) {
            return;
        }
        for ($i = 0; $i < $this->codeLength; $i++) {
            $newSymbol = str_shuffle($this->symbols)[rand(0, strlen($this->symbols) - 1)];
            $this->secretCode .= $newSymbol;
            if (in_array($newSymbol, $this->similarSymbols)) {
                $this->secretCodeV2 .= $this->similarSymbolsV2[array_search($newSymbol, $this->similarSymbols)];
            } elseif (in_array($newSymbol, $this->similarSymbolsV2)) {
                $this->secretCodeV2 .= $this->similarSymbols[array_search($newSymbol, $this->similarSymbolsV2)];
            } else {
                $this->secretCodeV2 .= $newSymbol;
            }
        }
    }
}

