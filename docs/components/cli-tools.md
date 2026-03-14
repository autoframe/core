*Cli Prompt, Text styles Bold, Italic, Blink, Underline, Text colors, Background colors...*

**Examples** https://prnt.sc/-ns-4QpB3NYl

![Examples](https://img001.prntscr.com/file/img001/j6aDkLc5SkG0jMaKxPMSmw.png)

```php
`AfrCliDetect`

AfrCliDetect::isCli() || AfrCliDetect::insideCli();

AfrCliDetect::isWeb() || AfrCliDetect::isHttpRequest();

```

---

```php
`AfrCliPromptMenu`

        if (!AfrCliPromptMenu::insideCli()) {
            echo 'The script does not run inside CLI!' . PHP_EOL;
            return;
        }

        $options = [
            'Mercedes',
            'Audi',
            'Porsche',
        ];

        $user_choice = AfrCliPromptMenu::promptMenu(
            "Select your dream car",
            $options,
            $options[1]
        );
        print PHP_EOL . "You chose: '$user_choice'\n";
```

---

```php
`AfrCliTextColors`

AfrCliTextColors::getInstance()->
        bgBlueLight('Hello ')->
        bgDefaultAllColorStyle( 'my ')->
        styleBold(true)->
        textAppend('bold ')->
        colorGreen('World! ')->
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
        textPrint();
```

---

```php
`AfrVendorDir`

AfrVendorDir::pathIsInsideVendorDir(__DIR__ || $sPath)->;

```

