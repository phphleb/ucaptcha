<?php


namespace Phphleb\Ucaptcha;


class Captcha implements CaptchaInterface
{
    const CHARS = '123456789ABCDFGHIJKLMNPQSXTUVWY';

    const SESSION_CAPTCHA_NAME = 'UCAPTCHA_VALUES';

    const SESSION_CAPTCHA_PASSED = 'UCAPTCHA_PASSED';

    const MAX_LIST_VALUES = 6;

    const TYPES = ['base', 'dark', '3d'];

    const TYPE_BASE = 'base';

    const TYPE_DARK = 'dark';

    const TYPE_3D = '3d';

    protected $code;

    protected $type = self::TYPE_BASE;

    public function construct()
    {
        error_reporting(0);
    }

    /**
     * Was at least one captcha pass successful.
     * Было ли успешным хоть одно прохождение капчи.
     *
     * @return bool
     */
    public function isPassed()
    {
        if (!isset($_SESSION)) @session_start();

        return !empty($_SESSION[self::SESSION_CAPTCHA_PASSED]) && $_SESSION[self::SESSION_CAPTCHA_PASSED] == 1;
    }

    /**
     * Checking the code entered by the user.
     * Проверка введенного пользователем кода.
     *
     * @param string $code
     * @return bool
     */
    public function check(string $code)
    {
        if (!isset($_SESSION)) @session_start();

        if (in_array(strlen($code), [5, 6]) && $this->checkAllCodeList($code)) {
            $_SESSION[self::SESSION_CAPTCHA_PASSED] = 1;

            return true;
        }
        return false;
    }

    /**
     * Displays a PNG image.
     * Отображает PNG-изображение.
     *
     * @param string $type
     */
    public function createImage(string $type = self::TYPE_BASE)
    {
        if (!isset($_SESSION)) @session_start();

        $this->type = in_array($type, self::TYPES) ? $type : self::TYPE_BASE;

        $_SESSION[self::SESSION_CAPTCHA_NAME][] = $this->getCode();
        if(count($_SESSION[self::SESSION_CAPTCHA_NAME]) > self::MAX_LIST_VALUES) {
            array_shift($_SESSION[self::SESSION_CAPTCHA_NAME]);
        }

        $firstBackground = imagecreatefrompng($this->getRandomBackground());
        $secondBackground = imagecreatefrompng($this->getRandomBackground());
        imagesavealpha($secondBackground, true);

        $this->createSymbols($firstBackground);

        if($this->type === self::TYPE_3D) {
            imagecopymerge($firstBackground, $secondBackground, 0, 0, 0, 0, 200, 120, rand(25, 35));
        }

        header('Content-type:image/png');
        header('Cache-Control: max-age=3600, must-revalidate');
        imagepng($firstBackground);
        imagedestroy($firstBackground);
        imagedestroy($secondBackground);
    }

    /**
     * @param resource|false $image
     * @return resource|false
     */
    private function createSymbols($image)
    {
        $code = $this->getCode();
        $lenght = strlen($code);
        $additive = ($lenght > 5) ? 0 : 5;
        $y = rand(3, 5); // Начальная координата высоты
        $x = rand(6, 7 + $additive) - 35; // Отступ от левого края

        for ($i = 0; $i < $lenght; $i++) {
            $x = $x + 30 + $additive;
            $symbol = $this->searchImage($code[$i]);
            $coefficient = rand(5, 8 + $additive); // Максимум
            $this->addInStage($x + rand(0, 4), $y + rand(0, 5), $symbol, $image, 30 + $coefficient, 30 + $coefficient);
        }

        return $image;
    }

    private function searchImage($symbol)
    {
        $files = [];
        $dir = opendir(__DIR__ . "/resources/{$this->type}/symbols/$symbol/");
        while (($currentfile = readdir($dir)) !== false) {
            if (!is_dir($currentfile)) {
                $files[] = $currentfile;
            }
        }
        closedir($dir);

        return __DIR__ . "/resources/{$this->type}/symbols/$symbol/" . $files[rand(0, count($files) - 1)];
    }

    private function addInStage($x, $y, $file, $image, $width, $height)
    {

        $angle = rand(-10, 10);
        $stamp = $this->imageResize($file, $width, $height);

        $stamp = imagerotate($stamp, $angle, imageColorAllocateAlpha($stamp, 0, 0, 0, 127));
        imagealphablending($stamp, false);
        imagealphablending($stamp, true);

        imagecopy($image, $stamp, $x, $y, 0, 0, imagesx($stamp), imagesy($stamp));

        return true;
    }

    private function imageResize($file, int $width, int $height)
    {
        $size = getimagesize($file);
        if ($size === false) {
            return false;
        }

        $format = strtolower(substr($size['mime'], strpos($size['mime'], '/') + 1));
        $function = 'imagecreatefrom' . $format;
        if (!function_exists($function)) {
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

        $ratio = min($ratioX, $ratioY);
        $useXRatio = ($ratioX == $ratio);

        $newWidth = $useXRatio ? $width : floor($size[0] * $ratio);
        $newHeight = !$useXRatio ? $height : floor($size[1] * $ratio);
        $newLeft = $useXRatio ? 0 : floor(($width - $newWidth) / 2);
        $newTop = !$useXRatio ? 0 : floor(($height - $newHeight) / 2);

        $isrc = $function($file);
        $idest = imagecreatetruecolor($width, $height);

        //imagerotate ( $idest, 90 , 0 );

        imagecolortransparent($idest, imagecolorallocate($idest, 0, 0, 0));
        imagealphablending($idest, false);
        imagesavealpha($idest, true);

        imagecopyresampled($idest, $isrc, $newLeft, $newTop, 0, 0, $newWidth, $newHeight, $size[0], $size[1]);
        imagedestroy($isrc);
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
        $lenght = rand(5, 6);
        $code = '';
        for ($i = 0; $i < $lenght; $i++) {
            $code .= substr(self::CHARS, rand(1, strlen(self::CHARS)) - 1, 1);
        }
        $codeList = preg_split('//', $code, -1, PREG_SPLIT_NO_EMPTY);
        shuffle($codeList);
        return implode("", $codeList);
    }

    /**
     * Получение рандомного фона
     */
    private function getRandomBackground()
    {
        $files = [];
        $dir = opendir(__DIR__ . "/resources/{$this->type}/background/");
        while (($currentfile = readdir($dir)) !== false) {
            if (!is_dir($currentfile)) {
                $files[] = $currentfile;
            }
        }
        closedir($dir);

        return __DIR__ . "/resources/{$this->type}/background/" . $files[rand(0, count($files) - 1)];
    }

    /**
     * Поиск совпадения в списке введенных кодов
     * @param string $code
     * @return bool
     */
    private function checkAllCodeList(string $code)
    {
        if (empty($_SESSION[self::SESSION_CAPTCHA_NAME])) {
            return false;
        }
        foreach ($_SESSION[self::SESSION_CAPTCHA_NAME] as $value) {
            if (strtoupper($value) === strtoupper($code)) {
                return true;
            }
        }
        return false;
    }
}

