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

    /**
     * Text discard buffer.
     */
    public function textDiscardBuffer(): self
    {
        $this->sTextBuffer = '';
        return $this;
    }

    /**
     * Text get.
     */
    public function textGet(bool $bDiscardBuffer = true): string
    {
        $sReturn = $this->sTextBuffer;
        if ($bDiscardBuffer) {
            $this->textDiscardBuffer();
        }
        return $sReturn;
    }

    /**
     * Text print.
     */
    public function textPrint(bool $bDiscardBuffer = true): self
    {
        echo $this->sTextBuffer;
        if ($bDiscardBuffer) {
            $this->textDiscardBuffer();
        }
        return $this;
    }

    /**
     * Text append.
     */
    public function textAppend(string $sToAppend): self
    {
        $this->sTextBuffer .= $sToAppend;
        return $this;
    }

    /**
     * Text prepend.
     */
    public function textPrepend(string $sTextToPrepend): self
    {
        $this->sTextBuffer = $sTextToPrepend . $this->sTextBuffer;
        return $this;
    }

    /**
     * Style default all bg color.
     */
    public function styleDefaultAllBgColor(string $sAppend = ''): self
    {
        return $this->textAppend(sprintf("\033[0m%s", $sAppend));
    }

    /**
     * Style bold.
     */
    public function styleBold(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "1m");
    }

    /**
     * Style dim.
     */
    public function styleDim(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "2m");
    }

    /**
     * Style italic.
     */
    public function styleItalic(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "3m");
    }

    /**
     * Style underlined.
     */
    public function styleUnderlined(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "4m");
    }

    /**
     * Style blink.
     */
    public function styleBlink(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "5m");
    }

    /**
     * Style6.
     */
    public function style6(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "6m");
    }

    /**
     * Style invert.
     */
    public function styleInvert(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "7m");
    }

    /**
     * Style hidden pwd.
     */
    public function styleHiddenPwd(bool $bOn): self
    {
        return $this->textAppend("\033[" . ($bOn ? '' : '2') . "8m");
    }

    /**
     * Color default all bg style.
     */
    public function colorDefaultAllBgStyle(string $sAppend = ''): self
    {
        return $this->textAppend("\033[0m" . $sAppend);
    }

    /**
     * Color default.
     */
    public function colorDefault(string $sAppend = ''): self
    {
        return $this->textAppend("\033[39m" . $sAppend);
    }

    /**
     * Color black.
     */
    public function colorBlack(string $sAppend = ''): self
    {
        return $this->textAppend("\033[30m" . $sAppend);
    }

    /**
     * Color red.
     */
    public function colorRed(string $sAppend = ''): self
    {
        return $this->textAppend("\033[31m" . $sAppend);
    }

    /**
     * Color green.
     */
    public function colorGreen(string $sAppend = ''): self
    {
        return $this->textAppend("\033[32m" . $sAppend);
    }

    /**
     * Color yellow.
     */
    public function colorYellow(string $sAppend = ''): self
    {
        return $this->textAppend("\033[33m" . $sAppend);
    }

    /**
     * Color blue.
     */
    public function colorBlue(string $sAppend = ''): self
    {
        return $this->textAppend("\033[34m" . $sAppend);
    }

    /**
     * Color magenta.
     */
    public function colorMagenta(string $sAppend = ''): self
    {
        return $this->textAppend("\033[35m" . $sAppend);
    }

    /**
     * Color cyan.
     */
    public function colorCyan(string $sAppend = ''): self
    {
        return $this->textAppend("\033[36m" . $sAppend);
    }

    /**
     * Color gray light.
     */
    public function colorGrayLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[37m" . $sAppend);
    }

    /**
     * Color gray dark.
     */
    public function colorGrayDark(string $sAppend = ''): self
    {
        return $this->textAppend("\033[90m" . $sAppend);
    }

    /**
     * Color red light.
     */
    public function colorRedLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[91m" . $sAppend);
    }

    /**
     * Color green light.
     */
    public function colorGreenLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[92m" . $sAppend);
    }

    /**
     * Color yellow light.
     */
    public function colorYellowLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[93m" . $sAppend);
    }

    /**
     * Color blue light.
     */
    public function colorBlueLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[94m" . $sAppend);
    }

    /**
     * Color magenta light.
     */
    public function colorMagentaLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[95m" . $sAppend);
    }

    /**
     * Color cyan light.
     */
    public function colorCyanLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[96m" . $sAppend);
    }

    /**
     * Color white.
     */
    public function colorWhite(string $sAppend = ''): self
    {
        return $this->textAppend("\033[97m" . $sAppend);
    }


    /**
     * Bg default all color style.
     */
    public function bgDefaultAllColorStyle(string $sAppend = ''): self
    {
        return $this->textAppend("\033[0m" . $sAppend);
    }

    /**
     * Bg default.
     */
    public function bgDefault(string $sAppend = ''): self
    {
        return $this->textAppend("\033[49m" . $sAppend);
    }

    /**
     * Bg black.
     */
    public function bgBlack(string $sAppend = ''): self
    {
        return $this->textAppend("\033[40m" . $sAppend);
    }

    /**
     * Bg red.
     */
    public function bgRed(string $sAppend = ''): self
    {
        return $this->textAppend("\033[41m" . $sAppend);
    }

    /**
     * Bg green.
     */
    public function bgGreen(string $sAppend = ''): self
    {
        return $this->textAppend("\033[42m" . $sAppend);
    }

    /**
     * Bg yellow.
     */
    public function bgYellow(string $sAppend = ''): self
    {
        return $this->textAppend("\033[43m" . $sAppend);
    }

    /**
     * Bg blue.
     */
    public function bgBlue(string $sAppend = ''): self
    {
        return $this->textAppend("\033[44m" . $sAppend);
    }

    /**
     * Bg magenta.
     */
    public function bgMagenta(string $sAppend = ''): self
    {
        return $this->textAppend("\033[45m" . $sAppend);
    }

    /**
     * Bg cyan.
     */
    public function bgCyan(string $sAppend = ''): self
    {
        return $this->textAppend("\033[46m" . $sAppend);
    }

    /**
     * Bg gray light.
     */
    public function bgGrayLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[47m" . $sAppend);
    }

    /**
     * Bg gray dark.
     */
    public function bgGrayDark(string $sAppend = ''): self
    {
        return $this->textAppend("\033[100m" . $sAppend);
    }

    /**
     * Bg red light.
     */
    public function bgRedLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[101m" . $sAppend);
    }

    /**
     * Bg green light.
     */
    public function bgGreenLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[102m" . $sAppend);
    }

    /**
     * Bg yellow light.
     */
    public function bgYellowLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[103m" . $sAppend);
    }

    /**
     * Bg blue light.
     */
    public function bgBlueLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[104m" . $sAppend);
    }

    /**
     * Bg magenta light.
     */
    public function bgMagentaLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[105m" . $sAppend);
    }

    /**
     * Bg cyan light.
     */
    public function bgCyanLight(string $sAppend = ''): self
    {
        return $this->textAppend("\033[106m" . $sAppend);
    }

    /**
     * Bg white.
     */
    public function bgWhite(string $sAppend = ''): self
    {
        return $this->textAppend("\033[107m" . $sAppend);
    }


    /**
     * Cursor save position.
     */
    public function cursorSavePosition(): self // sc
    {
        return $this->textAppend("\e7");
    }

    /**
     * Cursor restore position.
     */
    public function cursorRestorePosition(): self // rc
    {
        return $this->textAppend("\e8");
    }

    /**
     * Cursor clear screen move top left.
     */
    public function cursorClearScreenMoveTopLeft(): self // clear
    {
        return $this->textAppend("\e[H\e[2J");
    }

    /**
     * Cursor move up xrows.
     */
    public function cursorMoveUpXRows(int $iRows = 1): self // cuu
    {
        return $this->textAppend("\e[" . $iRows . 'A');
    }

    /**
     * Cursor move down xrows.
     */
    public function cursorMoveDownXRows(int $iRows = 1): self // cud
    {
        return $this->textAppend("\e[" . $iRows . 'B');
    }

    /**
     * Cursor move right xrows.
     */
    public function cursorMoveRightXRows(int $iRows = 1): self // cuf
    {
        return $this->textAppend("\e[" . $iRows . 'C');
    }

    /**
     * Cursor move left xrows.
     */
    public function cursorMoveLeftXRows(int $iRows = 1): self // cub
    {
        return $this->textAppend("\e[" . $iRows . 'D');
    }

    /**
     * Cursor move top left.
     */
    public function cursorMoveTopLeft(): self // home
    {
        return $this->textAppend("\e[H");
    }

    /**
     * Cursor move column.
     */
    public function cursorMoveColumn(int $iColumn = 1): self // hpa
    {
        return $this->textAppend("\e[" . $iColumn . 'G');
    }

    /**
     * Cursor move row xfirst column.
     */
    public function cursorMoveRowXFirstColumn(int $iRows = 1): self // vpa
    {
        return $this->textAppend("\e[" . $iRows . 'd');
    }

    /**
     * Cursor move row xcolumn x.
     */
    public function cursorMoveRowXColumnX(int $iRows = 1, int $iColumn = 1): self // cup
    {
        return $this->textAppend("\e[$iRows;" . $iColumn . 'H');
    }


    /**
     * Remove backspace.
     */
    public function removeBackspace(int $iChars = 1): self // dch
    {
        return $this->textAppend("\e[" . $iChars . 'P');
    }

    /**
     * Remove lines.
     */
    public function removeLines(int $iLines = 1): self // dl
    {
        return $this->textAppend("\e[" . $iLines . 'M');
    }

    /**
     * Remove chars.
     */
    public function removeChars(int $iChars = 1): self // ech
    {
        return $this->textAppend("\e[" . $iChars . 'X');
    }

    /**
     * Remove clear to bottom.
     */
    public function removeClearToBottom(): self // ed
    {
        return $this->textAppend("\eE[J");
    }

    /**
     * Remove clear to end of line.
     */
    public function removeClearToEndOfLine(): self // el
    {
        return $this->textAppend("\eE[K");
    }

    /**
     * Remove clear to start of line.
     */
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

    /**
     * Demo.
     */
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
