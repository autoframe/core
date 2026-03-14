<?php

namespace Autoframe\Core\CliTools;

class AfrCliPromptMenu
{
    /**
     * @param string $prompt
     * @param array $options
     * @param string $default
     * @param int $iMaxPrompts default is 2
     * @param string $sAvailableOptions Text: Available options
     * @param int $blue color index
     * @param int $red color index
     * @return mixed|string
     */
    public static function promptMenu(
        string $prompt = 'Choose One',
        array  $options = ['yes', 'no'],
        string $default = 'yes',
        int    $iMaxPrompts = 2,
        string $sAvailableOptions = 'Available options',
        int    $blue = 4,
        int    $red = 1
    )
    {
        /**
         * Create list of options to show user
         * If default is not in the list, add it and put at end of array
         */
        if (!empty($options) && !in_array($default, $options)) {
            $default = $options[0];
        }
        $options = array_merge([$default], array_diff($options, [$default]));

        /**
         * Key list of options by sequential letters, starting at 'a'
         */
        $lastLetter = chr(ord('a') + count($options) - 1);
        $selectOptions = range('a', $lastLetter);
        $options = array_combine($selectOptions, $options);

        /**
         * Prompt user to choose an option until they select either a valid value
         */
        echo "\n\033[3{$blue}m$sAvailableOptions: (" . implode(', ', array_keys($options)) . ")\033[0m\n";
        /**
         * Output the list of options with an asterisk beside the default value
         */
        foreach ($options as $select => $option) {
            if ($option == $default) {
                echo "\033[1m";
            }
            print "\t$select) $option";
            if ($option == $default) {
                echo "\033[0m";
            }
            echo PHP_EOL;
        }
        echo "\033[3{$blue}m$prompt:\033[0m ";

        while ($iMaxPrompts) {
            /**
             * Read one keystroke from the user
             */
            //    readline_callback_handler_install($prompt.PHP_EOL, function() {});
            $iMaxPrompts--;
            $streamRes = fopen('php://stdin', 'r');
            $keystrokeOrig = stream_get_contents($streamRes, 1);
            fclose($streamRes);

            /**
             * Return selected value if valid
             */
            $keystroke = strtolower((string)$keystrokeOrig);
            if (isset($options[$keystroke])) {
                return $options[$keystroke];
            }

	        if($keystrokeOrig===false || strlen($keystrokeOrig)<1){
		        echo "\n\033[3{$red}mUser input is unavailable. Defaulting to ($default)\033[0m \n";
		        return $default;
	        }

            /**
             * Return default if keystroke <RETURN>
             */
            if (ord($keystroke) == 10 || $iMaxPrompts < 1) {
                return $default;
            }



            /**
             * No valid choice. Show menu again
             */
            echo "\n\033[3{$red}m$sAvailableOptions:\033[3{$blue}m (" . implode(', ', array_keys($options)) . ")\033[0m \n";
            foreach ($options as $select => $option) {
                if ($option == $default) {
                    echo "\033[4{$blue}m";
                }
                print "$select) $option";
                if ($option == $default) {
                    echo "\033[49m";
                }
                echo PHP_EOL;
            }
            echo "\033[3{$red}m$prompt:\033[0m ";

        }
        return $default;
    }

    public static function demo(): void
    {
        if (!AfrCliHttpDetect::isCli()) {
            echo 'The script does runs inside CLI!' . PHP_EOL;
            return;
        }
        $options = [
            'Mercedes',
            'Audi',
            'Porsche',
        ];

        $user_choice = self::promptMenu(
            "Select your dream car",
            $options,
            $options[1]
        );
        print PHP_EOL . "You chose: '$user_choice'\n";
    }

}