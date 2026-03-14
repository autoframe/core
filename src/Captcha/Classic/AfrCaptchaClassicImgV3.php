<?php
declare(strict_types=1);


namespace Autoframe\Core\Captcha\Classic;

class AfrCaptchaClassicImgV3 extends AfrCaptchaClassicImg
{

    protected int $ImgVersion = 3;

    function getHtmlCaptcha(): string
    {

        return 'alfa 1.3';
    }


    /**
     * Create image.
     */
    public function createImage(string $sCode)
    {

        $bLines = true;
        $iBgcol = 50; // + 50
        $bgtextcol = 80; // + 50
        $textcol = 205; // + 50 // TODO inbunatatire vizibilitate text culoare
        $textcol = 155; // + 50

        $iCodeLength = strlen($sCode);
        list(
            $iWidth,
            $iHeight,
            $iFontSize
            ) = $this->getImageParameters($iCodeLength, 0.8);

        $this->initImage($iWidth, $iHeight, true);



        $aTextColors = $aFakeTextColors = [];
        for ($i = 0; $i < $iCodeLength; $i++) {
            $aTextColors[] = $this->imagecolorallocateRandRGB($textcol, $textcol + 50);
            $aFakeTextColors[] = $this->imagecolorallocateRandRGB($bgtextcol, $bgtextcol + 50);
        }
        // define background color - never the same, close to black
        $this->imageFillColor($this->imagecolorallocateRandRGB($iBgcol,$iBgcol + 30));

        $this->writeFakeCaptchaTextOnImage($sCode, $iFontSize, $aFakeTextColors);
        $this->drawLines($this->image, $aFakeTextColors[rand(0,count($aFakeTextColors)-1)], $iWidth, $iHeight, ['/','\\','x','+'][rand(0,3)],0.75);
        imagecopyresampled($this->image, $this->image, 0, 0, 0, 0, $iWidth+1, $iHeight+1, $iWidth, $iHeight);
        $this->writeCaptchaTextOnImage($sCode, $iFontSize, $aTextColors);

        if ($bLines && $iHeight > 50 && FALSE) { //TODO
            // throw in some lines
            $this->imagelinethick(
                $this->image, rand(0, 10),
                rand(0, $iHeight / 2),
                rand($iWidth - 10, $iWidth),
                rand($iHeight / 2, $iHeight),
                $aTextColors[rand(0, $iCodeLength - 1)],
                round(rand((int)ceil($iHeight/70), (int)ceil($iHeight/50)) )
            );
        }
        if ($bLines && $iHeight > 90 && FALSE) { //TODO
            // throw in some lines
            $this->imagelinethick(
                $this->image, rand(($iWidth / 2) - 10, $iWidth / 2),
                rand($iHeight / 2, $iHeight),
                rand(($iWidth / 2) + 10, $iWidth),
                rand(0, ($iHeight / 2)),
                $aTextColors[rand(0, $iCodeLength - 1)],
                round(rand((int)ceil($iHeight/70), (int)ceil($iHeight/50)) )
            );
        }

        for ($i = 0; $i < $iCodeLength; $i++) {
            $this->generateRandomDots(
                $this->image,
                $aTextColors[$i],
                $iWidth,
                $iHeight,
                $this->fFontFactor * 0.05 / $iCodeLength * 4
            );
        }

    }


}
