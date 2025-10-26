<?php

namespace Autoframe\Core\CliTools;

use Exception;

/**
 * getInstance()->styleBoldSet()->textAppend('Hello world!')->styleDefaultAll()->textPrint();
 * https://misc.flogisoft.com/bash/tip_colors_and_formatting
 * https://wiki.archlinux.org/title/Bash/Prompt_customization
 *
 * $oTxt = AfrCliTextColors::getInstance()->
 * bgBlueLight('Hello ')->
 * styleBold(true)->
 * colorGreen('World! ')->
 * styleBold(false)->
 * bgMagenta('How ')->
 * styleInvert(true)->
 * textAppend('Inverted ')->
 * styleInvert(false)->
 * bgCyanLight()->
 * colorYellowLight('is the ')->
 * styleItalic(true)->
 * colorRed('rainbow?')->
 * styleDefaultAllBgColor()->
 * textPrint();
 */
class AfrCliTextColors
{

    protected static AfrCliTextColors $oInstance;

    protected string $sTextBuffer = '';

    public function textDiscardBuffer(): self
    {
        $this->sTextBuffer = '';
        return $this;
    }

    public function textGet(bool $bDiscardBuffer = true): string
    {
        $sReturn = $this->sTextBuffer;
        if ($bDiscardBuffer) {
            $this->textDiscardBuffer();
        }
        return $sReturn;
    }

    public function textPrint(bool $bDiscardBuffer = true): self
    {
        echo $this->sTextBuffer;
        if ($bDiscardBuffer) {
            $this->textDiscardBuffer();
        }
        return $this;
    }

    public function textAppend(string $sToAppend): self
    {
        $this->sTextBuffer .= $sToAppend;
        return $this;
    }

    public function textPrepend(string $sTextToPrepend): self
    {
        $this->sTextBuffer = $sTextToPrepend . $this->sTextBuffer;
        return $this;
    }

    public function styleDefaultAllBgColor(string $sAppend = ''): self
    {
        return $this->textAppend(sprintf("\033[0m%s", $sAppend));
    }

    public function styleBold(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "1m");
    }

    public function styleDim(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "2m");
    }

    public function styleItalic(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "3m");
    }

    public function styleUnderlined(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "4m");
    }

    public function styleBlink(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "5m");
    }

    public function style6(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "6m");
    }

    public function styleInvert(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "7m");
    }

    public function styleHiddenPwd(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "8m");
    }

    public function colorDefaultAllBgStyle(string $sAppend = ''): self
    {
        return $this->textAppend("\033[0m" . $sAppend);
    }

    public function colorDefault(string $sAppend = ''): self
    {
        return $this->textAppend("\033[39m" . $sAppend);
    }

    public function colorBlack(string $sAppend = ''): self
    {
        return $this->textAppend("\033[30m" . $sAppend);
    }

    public function colorRed(string $sAppend = ''): self
    {
        return $this->textAppend("\033[31m" . $sAppend);
    }

    public function colorGreen(string $sAppend = ''): self
    {
        return $this->textAppend("\033[32m" . $sAppend);
    }

    public function colorYellow(string $sAppend = ''): self
    {
        return $this->textAppend("\033[33m" . $sAppend);
    }

    public function colorBlue(string $sAppend = ''): self
    {
        return $this->textAppend("\033[34m" . $sAppend);
    }

    public function colorMagenta(string $sAppend = ''): self
    {
        return $this->textAppend("\033[35m" . $sAppend);
    }

    public function colorCyan(string $sAppend = ''): self
    {
        return $this->textAppend("\033[36m" . $sAppend);
    }

    public function colorGrayLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[37m" . $sAppend);
    }

    public function colorGrayDark(string $sAppend = ''): self
    {
        return $this->textAppend("\033[90m" . $sAppend);
    }

    public function colorRedLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[91m" . $sAppend);
    }

    public function colorGreenLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[92m" . $sAppend);
    }

    public function colorYellowLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[93m" . $sAppend);
    }

    public function colorBlueLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[94m" . $sAppend);
    }

    public function colorMagentaLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[95m" . $sAppend);
    }

    public function colorCyanLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[96m" . $sAppend);
    }

    public function colorWhite(string $sAppend = ''): self
    {
        return $this->textAppend("\033[97m" . $sAppend);
    }


    public function bgDefaultAllColorStyle(string $sAppend = ''): self
    {
        return $this->textAppend("\033[0m" . $sAppend);
    }

    public function bgDefault(string $sAppend = ''): self
    {
        return $this->textAppend("\033[49m" . $sAppend);
    }

    public function bgBlack(string $sAppend = ''): self
    {
        return $this->textAppend("\033[40m" . $sAppend);
    }

    public function bgRed(string $sAppend = ''): self
    {
        return $this->textAppend("\033[41m" . $sAppend);
    }

    public function bgGreen(string $sAppend = ''): self
    {
        return $this->textAppend("\033[42m" . $sAppend);
    }

    public function bgYellow(string $sAppend = ''): self
    {
        return $this->textAppend("\033[43m" . $sAppend);
    }

    public function bgBlue(string $sAppend = ''): self
    {
        return $this->textAppend("\033[44m" . $sAppend);
    }

    public function bgMagenta(string $sAppend = ''): self
    {
        return $this->textAppend("\033[45m" . $sAppend);
    }

    public function bgCyan(string $sAppend = ''): self
    {
        return $this->textAppend("\033[46m" . $sAppend);
    }

    public function bgGrayLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[47m" . $sAppend);
    }

    public function bgGrayDark(string $sAppend = ''): self
    {
        return $this->textAppend("\033[100m" . $sAppend);
    }

    public function bgRedLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[101m" . $sAppend);
    }

    public function bgGreenLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[102m" . $sAppend);
    }

    public function bgYellowLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[103m" . $sAppend);
    }

    public function bgBlueLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[104m" . $sAppend);
    }

    public function bgMagentaLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[105m" . $sAppend);
    }

    public function bgCyanLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[106m" . $sAppend);
    }

    public function bgWhite(string $sAppend = ''): self
    {
        return $this->textAppend("\033[107m" . $sAppend);
    }


    public function cursorSavePosition(): self // sc
    {
        return $this->textAppend("\e7");
    }

    public function cursorRestorePosition(): self // rc
    {
        return $this->textAppend("\e8");
    }

    public function cursorClearScreenMoveTopLeft(): self // clear
    {
        return $this->textAppend("\e[H\e[2J");
    }

    public function cursorMoveUpXRows(int $iRows = 1): self // cuu
    {
        return $this->textAppend("\e[" . $iRows . 'A');
    }

    public function cursorMoveDownXRows(int $iRows = 1): self // cud
    {
        return $this->textAppend("\e[" . $iRows . 'B');
    }

    public function cursorMoveRightXRows(int $iRows = 1): self // cuf
    {
        return $this->textAppend("\e[" . $iRows . 'C');
    }

    public function cursorMoveLeftXRows(int $iRows = 1): self // cub
    {
        return $this->textAppend("\e[" . $iRows . 'D');
    }

    public function cursorMoveTopLeft(): self // home
    {
        return $this->textAppend("\e[H");
    }

    public function cursorMoveColumn(int $iColumn = 1): self // hpa
    {
        return $this->textAppend("\e[" . $iColumn . 'G');
    }

    public function cursorMoveRowXFirstColumn(int $iRows = 1): self // vpa
    {
        return $this->textAppend("\e[" . $iRows . 'd');
    }

    public function cursorMoveRowXColumnX(int $iRows = 1, int $iColumn = 1): self // cup
    {
        return $this->textAppend("\e[$iRows;" . $iColumn . 'H');
    }


    public function removeBackspace(int $iChars = 1): self // dch
    {
        return $this->textAppend("\e[" . $iChars . 'P');
    }

    public function removeLines(int $iLines = 1): self // dl
    {
        return $this->textAppend("\e[" . $iLines . 'M');
    }

    public function removeChars(int $iChars = 1): self // ech
    {
        return $this->textAppend("\e[" . $iChars . 'X');
    }

    public function removeClearToBottom(): self // ed
    {
        return $this->textAppend("\eE[J");
    }

    public function removeClearToEndOfLine(): self // el
    {
        return $this->textAppend("\eE[K");
    }

    public function removeClearToStartOfLine(): self // el1
    {
        return $this->textAppend("\eE[1K");
    }

    final protected function __construct()
    {
    }

    /**
     * @throws Exception
     */
    final public function __clone()
    {
        throw new Exception('Cannot clone a singleton: ' . static::class);
    }

    /**
     * @throws Exception
     */
    final public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton: ' . static::class);
    }

    final public static function getInstance(): self
    {
        if (empty(self::$oInstance)) {
            self::$oInstance = new static();
        }
        return self::$oInstance;
    }

    public static function demo(): void
    {
        if(!AfrCliHttpDetect::isCli()){
            echo 'The script does not run inside CLI!'.PHP_EOL;
            return;
        }
        static::getInstance()->
        bgBlueLight('Hello ')->
        bgDefaultAllColorStyle( 'my ')->
        styleBold(true)->
        textAppend('bold ')->
        colorGreen('World! ')->
        colorRed(__CLASS__)->
        colorBlue(' ; ')->
        styleBold(false)->
        bgMagenta('How ')->
        styleInvert(true)->
        textAppend('Inverted ')->
        styleInvert(false)->
        bgCyanLight()->
        colorYellowLight('is the ')->
        styleItalic(true)->
        colorRed('rainbow?')->
        styleDefaultAllBgColor()->
        textAppend("\n")->
        textPrint();
    }
}
