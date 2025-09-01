<?php

declare(strict_types=1);

namespace Artemeon\Confluence\MacroReplacer;

/**
 * Ersetze <ac:image> durch HTML <img> oder <video> Tags, abhängig vom Dateityp
 */
class ImageAndVideoMacroReplacer implements MacroReplacerInterface
{
    private string $sourceFolder;

    public function __construct(string $sourceFolder = '')
    {
        if (! str_ends_with($sourceFolder, '/')) {
            $sourceFolder .= '/';
        }

        $this->sourceFolder = $sourceFolder;
    }

    public function replace(string $haystack): string
    {
        return preg_replace_callback(
            '/<ac:image[^>]*>.*?<ri:attachment\s+ri:filename="([^"]+)"[^>]*>.*?(?:<ac:caption>(.*?)<\/ac:caption>)?.*?<\/ac:image>/is',
            function ($match) {
                $attachmentFileName = $match[1];
                $caption = isset($match[2]) ? trim($match[2]) : '';
                $fileExtension = pathinfo($attachmentFileName, PATHINFO_EXTENSION);

                // Distinguish between images and videos
                if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $imgTag = '<img src="'.$this->sourceFolder.$attachmentFileName.'" alt="could not load image">';
                    if ($caption !== '') {
                        return '<figure>'.$imgTag.'<figcaption class="text-center">'.$caption.'</figcaption></figure>';
                    }

                    return $imgTag;
                } elseif (in_array($fileExtension, ['mp4', 'avi', 'mkv', 'mov'])) {
                    return '<video controls><source src="'.$this->sourceFolder.$attachmentFileName.'" type="video/'.$fileExtension.'">Your browser does not support the video tag.</video>';
                } else {
                    // By default, a link to the attachment is created
                    return '<a href="'.$this->sourceFolder.$attachmentFileName.'">'.$this->sourceFolder.$attachmentFileName.'</a>';
                }
            },
            $haystack
        );
    }
}
