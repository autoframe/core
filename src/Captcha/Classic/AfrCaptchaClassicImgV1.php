<?php
declare(strict_types=1);


namespace Autoframe\Core\Captcha\Classic;


class AfrCaptchaClassicImgV1 extends AfrCaptchaClassicImg
{

    protected int $ImgVersion = 1;

    function getHtmlCaptcha(): string
    {

        return 'alfa 1.1';
    }




    /**
     * Create image.
     */
    public function createImage(string $sCode)
    {
        $iCodeLength = strlen($sCode);
        list(
            $iWidth,
            $iHeight,
            $iFontSize
            ) = $this->getImageParameters($iCodeLength, 0.79);

        $this->initImage($iWidth, $iHeight, false);

        $base = 127;
        $iSmlDelta = 32; //multiplii de 4

        $aBaseColor = $this->generateRandRGB($base + $iSmlDelta, $base + intval($iSmlDelta * 3.5), false);
        $base_color = $this->imagecolorallocateAData($aBaseColor);
        $this->imageFillColor($base_color);


        //$aNoiseColorDelta = $this->generateRandRGB(6, 14, true);
        //$aNoiseColor = array($aBaseColor[0] + $aNoiseColorDelta[0], $aBaseColor[1] + $aNoiseColorDelta[1], $aBaseColor[2] + $aNoiseColorDelta[2]);
        $aNoiseColor = $this->generateRandRGB(15, 20, true, $aBaseColor);
        $noise_color = $this->imagecolorallocateAData($aNoiseColor);

        //$this->drawLines($this->image, $noise_color, $iWidth, $iHeight, '+x',1);

        // generate random lines in background
        $this->generateRandomDotLines($this->image, $noise_color, $iWidth, $iHeight, $this->fFontFactor, 1);

        $aTextColors = [];
        $iCooldown = 0;
        for ($i = 0; $i < $iCodeLength; $i++) {
            $aLetterDelta = $this->generateRandRGB($iSmlDelta * 2, $base + $iSmlDelta - 8, false);
            $aLetterColor = $this->sumRGBArrays($aBaseColor,$aLetterDelta,false);
            $aTextColors[$i] = $this->imagecolorallocateAData($aLetterColor);
            if(mt_rand(1, $iCodeLength+$iCooldown)%$iCodeLength===0){
                if(rand(0,1)){
                    $this->imagelinethick($this->image, mt_rand(0, $iWidth), mt_rand(0, $iHeight), mt_rand(0, $iWidth), mt_rand(0, $iHeight), $aTextColors[$i], rand(1, (int)ceil($iHeight / 60)));
                }
                else{
                    $iCooldown+=2;
                    $this->drawLines($this->image, $aTextColors[$i], $iWidth, $iHeight, ['-','|','/','\\'][rand(0,3)],0.75);
                }

            }
        }

        if ($iHeight > 50) {
            // throw in some lines
            $this->imagelinethick(
                $this->image, rand(0, 10),
                rand(0, $iHeight / 2),
                rand($iWidth - 10, $iWidth),
                rand($iHeight / 2, $iHeight),
                $aTextColors[rand(0, $iCodeLength - 1)],
                round(rand((int)ceil($iHeight/60), (int)ceil($iHeight / 40)) )
            );
        }
        if ($iHeight > 90) {
            // throw in some lines
            $this->imagelinethick(
                $this->image, rand(($iWidth / 2) - 10, $iWidth / 2),
                rand($iHeight / 2, $iHeight),
                rand(($iWidth / 2) + 10, $iWidth),
                rand(0, ($iHeight / 2)),
                $aTextColors[rand(0, $iCodeLength - 1)],
                round(rand((int)ceil($iHeight/60), (int)ceil($iHeight / 40)) )
            );
        }




        for ($i = 0; $i < $iCodeLength; $i++) {
            if (mt_rand(0, $iCodeLength - 1) % $iCodeLength === 0) {
                $this->arcLines($this->image, $aTextColors[$i], $iWidth, $iHeight, $iFontSize, 1);
            }
        }
        $this->writeCaptchaTextOnImage($sCode, $iFontSize, $aTextColors);

        for ($i = 0; $i < $iCodeLength; $i++) {
            $this->generateRandomDots($this->image, $aTextColors[$i], $iWidth, $iHeight, $this->fFontFactor * 0.026);
        }

        $this->generateRandomDots($this->image, $noise_color, $iWidth, $iHeight, $this->fFontFactor * 0.1);
        $this->generateRandomDots($this->image, $base_color, $iWidth, $iHeight, $this->fFontFactor * 0.1);
    }


}
