<?php


namespace Phphleb\Ucaptcha;


interface CaptchaInterface
{
    /**
     * Возвращает активный тип капчи
     * @return string
     */
    function getType(): string;

    /**
     * Устанавливает тип капчи
     * @param string $type
     */
    function setType(string $type);

    /**
     * Возвращает используемый уровень сложности
     * @return int
     */
    function getAttentionLevel(): int;

    /**
     * Устанавливает уровень сложности
     * @param int $level
     */
    function setAttentionLevel(int $level);

    /**
     * Возвращает данные изображения c капчей
     * @return mixed
     */
    function getImageRawData();

    /**
     * Возвращает закодированный в изображении код
     * (похожие варианты в массиве для сравнения)
     * @return array
     */
    function getSecretCode(): array;

}

