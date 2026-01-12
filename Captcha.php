<?php

declare(strict_types=1);

namespace Phphleb\Ucaptcha;

class Captcha implements CaptchaInterface
{
    const CHARS = '123456789ABCDFGHIJKLMNPQSXTUVWY';

    const SESSION_CAPTCHA_NAME = 'UCAPTCHA_VALUES';

    const SESSION_CAPTCHA_PASSED = 'UCAPTCHA_PASSED';

    const MAX_LIST_VALUES = 6;

    const TYPES = ['base', 'dark', '3d'];

    const FONTS = ['/verdana/Verdana.ttf', '/verdana/Verdana-Bold.ttf', '/arial/Arial-Black.ttf'];

    const TYPE_BASE = 'base';

    const TYPE_DARK = 'dark';

    const TYPE_3D = '3d';

    protected $code;

    protected $type = self::TYPE_BASE;

    /**
     * @var array|null
     */
    protected $session = null;

    /**
     * For other session implementations, they can be set as an array.
     *
     * Для иных реализаций сессий их можно установить как массив.
     */
    public function __construct(?array $session = null)
    {
        $this->session = $session;
    }

    /**
     * Obtaining the final session is used for another implementation of the session.
     *
     * Получение итоговой сессии, используется для иной реализации сессии.
     */
    public function getSession(): array
    {
        return $this->session === null ? ($_SESSION ?? []) : $this->session;
    }

    /**
     * Was at least one captcha pass successful.
     * The action does not change the session.
     *
     * Было ли успешным хоть одно прохождение капчи.
     * Действие не изменяет сессию.
     *
     *
     * @return bool
     */
    public function isPassed()
    {
        if ($this->session !== null) {
            return !empty($this->session[self::SESSION_CAPTCHA_PASSED]) && $this->session[self::SESSION_CAPTCHA_PASSED] == 1;
        }
        if (!isset($_SESSION)) @\session_start();

        return !empty($_SESSION[self::SESSION_CAPTCHA_PASSED]) && $_SESSION[self::SESSION_CAPTCHA_PASSED] == 1;
    }

    /**
     * Checking the code entered by the user.
     * The action modifies the session.
     *
     * Проверка введенного пользователем кода.
     * Действие изменяет сессию.
     *
     * @param string $code
     * @return bool
     */
    public function check(string $code)
    {
        if ($this->session !== null) {
            if (\in_array(\strlen($code), [5, 6]) && $this->checkAllCodeList($code)) {
                $this->session[self::SESSION_CAPTCHA_PASSED] = 1;

                return true;
            }
            return false;
        }
        if (!isset($_SESSION)) @\session_start();

        if (\in_array(\strlen($code), [5, 6]) && $this->checkAllCodeList($code)) {
            $_SESSION[self::SESSION_CAPTCHA_PASSED] = 1;

            return true;
        }
        return false;
    }

    /**
     * Displays a PNG image.
     * The action modifies the session.
     *
     * Отображает PNG-изображение.
     * Действие изменяет сессию.
     *
     * @param string $type
     *
     * @param bool $withoutHeaders - do not add headers.
     *                             - не добавлять заголовки.
     *
     * @param bool $withoutErrors - disable error output.
     *                             - отключить вывод ошибок.
     */
    public function createImage(string $type = self::TYPE_BASE, bool $withoutHeaders = false, bool $withoutErrors = true)
    {
        if ($withoutErrors) {
            \ini_set('display_errors', '0');
            \ini_set('display_startup_errors', '0');
            \error_reporting(E_ALL & ~E_WARNING);
        }

        $this->type = \in_array($type, self::TYPES) ? $type : self::TYPE_BASE;

        if ($this->session !== null) {
            $this->session[self::SESSION_CAPTCHA_NAME][] = $this->getCode();
            if (count($this->session[self::SESSION_CAPTCHA_NAME]) > self::MAX_LIST_VALUES) {
                \array_shift($this->session[self::SESSION_CAPTCHA_NAME]);
            }
        } else {
            if (!isset($_SESSION)) @\session_start();
            $_SESSION[self::SESSION_CAPTCHA_NAME][] = $this->getCode();
            if (count($_SESSION[self::SESSION_CAPTCHA_NAME]) > self::MAX_LIST_VALUES) {
                \array_shift($_SESSION[self::SESSION_CAPTCHA_NAME]);
            }
        }
        $firstBackground = \imagecreatefrompng($this->getRandomBackground());
        $secondBackground = \imagecreatefrompng($this->getRandomBackground());

        \imagesavealpha($secondBackground, true);

        $this->createSymbols($firstBackground);

        if ($this->type === self::TYPE_3D) {
            \imagecopymerge($firstBackground, $secondBackground, 0, 0, 0, 0, 200, 120, rand(25, 35));
        }

        if (!$withoutHeaders) {
            \header('Content-type:image/png');
            \header('Cache-Control: max-age=3600, must-revalidate');
        }
        \imagepng($firstBackground);

        if (PHP_VERSION_ID < 80000) {
            \imagedestroy($firstBackground);
            \imagedestroy($secondBackground);
        }
    }

    /**
     * @param resource|false $image
     * @return resource|false
     */
    private function createSymbols($image)
    {
        $code = $this->getCode();
        $lenght = \strlen($code);
        $additive = ($lenght > 5) ? 0 : 5;
        $y = \rand(3, 5); // Начальная координата высоты
        $x = \rand(6, 7 + $additive) - 35; // Отступ от левого края

        for ($i = 0; $i < $lenght; $i++) {
            $x = $x + 30 + $additive;
            $symbol = $this->type === self::TYPE_3D ? $this->searchImage($code[$i]) : $code[$i];
            $coefficient = \rand(5, 8 + $additive);
            $this->addInStage($x + \rand(0, 4), $y + \rand(0, 5), $symbol, $image, 30 + $coefficient, 30 + $coefficient);
        }

        return $image;
    }

    private function searchImage($symbol)
    {
        $files = [];
        $dir = \opendir(__DIR__ . "/resources/{$this->type}/symbols/$symbol/");
        while (($currentfile = \readdir($dir)) !== false) {
            if (!\is_dir($currentfile)) {
                $files[] = $currentfile;
            }
        }
        \closedir($dir);

        return __DIR__ . "/resources/{$this->type}/symbols/$symbol/" . $files[\rand(0, \count($files) - 1)];
    }

    private function addInStage($x, $y, $keyOrPath, $image, $width, $height)
    {
        $angle = \rand(-10, 10);
        if (\strlen($keyOrPath) > 1) {
            $stamp = $this->imageResize($keyOrPath, $width, $height);
            $stamp = \imagerotate($stamp, $angle, imageColorAllocateAlpha($stamp, 0, 0, 0, 127));
            \imagealphablending($stamp, false);
            \imagealphablending($stamp, true);
            \imagecopy($image, $stamp, $x, $y, 0, 0, \imagesx($stamp), \imagesy($stamp));
        } else {
            if (\rand(0, 2) === 1) {
                $keyOrPath = \strtolower($keyOrPath);
                $height += 5;
            }
            $font = __DIR__ . '/resources/fonts' . self::FONTS[\rand(0, \count(self::FONTS) - 1)];
            $color = \imagecolorallocate($image, 162, 165, 164);
            \imagettftext($image, $height - 8, $angle, $x, $y + 33, $color, $font, $keyOrPath);
        }

        return true;
    }

    private function imageResize($file, int $width, int $height)
    {
        $size = \getimagesize($file);
        if ($size === false) {
            return false;
        }

        $format = \strtolower(\substr($size['mime'], \strpos($size['mime'], '/') + 1));
        $function = 'imagecreatefrom' . $format;
        if (!\function_exists($function)) {
            return false;
        }
        $ratioX = $width / $size[0];
        $ratioY = $height / $size[1];

        if ($height == 0) {
            $ratioY = $ratioX;
            $height = $ratioY * $size[1];
        } else if ($width == 0) {
            $ratioX = $ratioY;
            $width = $ratioX * $size[0];
        }

        $ratio = \min($ratioX, $ratioY);
        $useXRatio = ($ratioX == $ratio);

        $newWidth = $useXRatio ? $width : \floor($size[0] * $ratio);
        $newHeight = !$useXRatio ? $height : \floor($size[1] * $ratio);
        $newLeft = $useXRatio ? 0 : \floor(($width - $newWidth) / 2);
        $newTop = !$useXRatio ? 0 : \floor(($height - $newHeight) / 2);
        $isrc = @$function($file);

        $idest = \imagecreatetruecolor((int)$width, (int)$height);

        //imagerotate ( $idest, 90 , 0 );

        \imagecolortransparent($idest, \imagecolorallocate($idest, 0, 0, 0));
        \imagealphablending($idest, false);
        \imagesavealpha($idest, true);

        \imagecopyresampled($idest, $isrc, (int)$newLeft, (int)$newTop, 0, 0, (int)$newWidth, (int)$newHeight, (int)$size[0], (int)$size[1]);

        if (PHP_VERSION_ID < 80000) {
            \imagedestroy($isrc);
        }

        return $idest;
    }

    /**
     * Возвращает код капчи
     * @return string
     */
    private function getCode()
    {
        if (empty($this->code)) {
            $this->code = $this->generateCode();
        }
        return $this->code;
    }

    /**
     * @return string
     */
    private function generateCode()
    {
        $lenght = \rand(5, 6);
        $code = '';
        for ($i = 0; $i < $lenght; $i++) {
            $code .= \substr(self::CHARS, \rand(1, \strlen(self::CHARS)) - 1, 1);
        }
        $codeList = \preg_split('//', $code, -1, PREG_SPLIT_NO_EMPTY);
        \shuffle($codeList);
        return \implode("", $codeList);
    }

    /**
     * Получение рандомного фона
     */
    private function getRandomBackground()
    {
        $files = [];
        $dir = \opendir(__DIR__ . "/resources/{$this->type}/background/");
        while (($currentfile = \readdir($dir)) !== false) {
            if (!\is_dir($currentfile)) {
                $files[] = $currentfile;
            }
        }
        \closedir($dir);

        return __DIR__ . "/resources/{$this->type}/background/" . $files[\rand(0, \count($files) - 1)];
    }

    /**
     * Поиск совпадения в списке введенных кодов
     * @param string $code
     * @return bool
     */
    private function checkAllCodeList(string $code)
    {
        if ($this->session !== null) {
            if (empty($this->session[self::SESSION_CAPTCHA_NAME])) {
                return false;
            }
            foreach ($this->session[self::SESSION_CAPTCHA_NAME] as $value) {
                if (\strtoupper($value) === \strtoupper($code)) {
                    return true;
                }
            }
            return false;
        }
        if (empty($_SESSION[self::SESSION_CAPTCHA_NAME])) {
            return false;
        }
        foreach ($_SESSION[self::SESSION_CAPTCHA_NAME] as $value) {
            if (\strtoupper($value) === \strtoupper($code)) {
                return true;
            }
        }
        return false;
    }
}

