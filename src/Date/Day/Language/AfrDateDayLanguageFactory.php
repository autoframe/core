<?php
declare(strict_types=1);

namespace Autoframe\Core\Date\Day\Language;

class AfrDateDayLanguageFactory implements AfrDateDayLanguageInterface
{
    private string $sLanguageClass;

    /**
     * Create a new instance.
     * @param string $sLanguage
     */
    public function __construct(string $sLanguage = '')
    {
        $sNsClassBase = __NAMESPACE__ . '\\AfrDateDayLanguage';
        if (!$sLanguage) {
            //TODO get from framework config
        }

        if ($sLanguage) {
            $sLanguageClass = $sNsClassBase . ucwords(strtolower(substr($sLanguage, 0, 2)));
            if (class_exists($sLanguageClass)) {
                $this->sLanguageClass = $sLanguageClass;
                return;
            }
        }
        $this->sLanguageClass = $sNsClassBase . 'En';

    }

    /**
     * Get day names.
     * @return string[]
     */
    public function getDayNames(): array
    {
        return (new $this->sLanguageClass())->getDayNames();
    }

    /**
     * Get day names short.
     * @return string[]
     */
    public function getDayNamesShort(): array
    {
        return (new $this->sLanguageClass())->getDayNamesShort();
    }
}
