UCaptcha
=====================

 Install using Composer:
 ```bash
 $ composer require phphleb/ucaptcha
 ```
-----------------------------------------

Шаг первый. Создание изображения (отображает PNG).

Step one. Image creation (displays PNG).

 ```php
(new \Phphleb\Ucaptcha\Captcha())->createImage(\Phphleb\Ucaptcha\Captcha::TYPE_BASE);
 ```
-----------------------------------------

Шаг второй. Проверка кода, введённого пользователем.

Step two. Checking the code entered by the user.

 ```php
if ((new \Phphleb\Ucaptcha\Captcha())->check($code)) {
  // Characters entered correctly.
} else {
  // Invalid characters.
};
 ```

-----------------------------------------

Проверка успешного прохождения капчи за текущую пользовательскую сессию.

Checking the successful completion of the captcha for the current user session.

 ```php
if ((new \Phphleb\Ucaptcha\Captcha())->isPassed()) {
  // The captcha has already been completed earlier.
} 
 ```
