<?php
namespace Leoloso\BlockMetadata;

/**
 * Manages the block metadata
 */
class Metadata {
    /**
     * Process all (Gutenberg) blocks' metadata into a medium-agnostic format from a WordPress post
     */
    public static function get_block_metadata($block_data)
    {
        $ret = [];
        foreach ($block_data as $block) {
            $blockMeta = null;
            switch ($block['blockName']) {
                case 'core/paragraph':
                    $blockMeta = [
                        'content' => trim(self::strip_tags($block['innerHTML'])),
                        'dir' => $block['attrs']['direction'],
                        'lang' => $block['attrs']['language'],
                        'className' => $block['attrs']['className']
                    ];
                    break;

                case 'core/image':
                    $blockMeta = [];
                    // If inserting the image from the Media Manager, it has an ID
                    $image_id = $block['attrs']['id'];
                    if (isset($image_id)) {
                        $small_image = wp_get_attachment_image_src($image_id, 'medium');
                        $medium_image = wp_get_attachment_image_src($image_id, 'medium_large');
                        $large_image = wp_get_attachment_image_src($image_id, 'full');
                        $blockMeta = [
                            'small' => [
                                'src' => \parse_url($small_image[0], PHP_URL_PATH),
                                'width' => $small_image[1],
                                'height' => $small_image[2],
                            ],
                            'medium' => [
                                'src' => \parse_url($medium_image[0], PHP_URL_PATH),
                                'width' => $medium_image[1],
                                'height' => $medium_image[2],
                            ],
                            'large' => [
                              'src' => \parse_url($large_image[0], PHP_URL_PATH),
                              'width' => $large_image[1],
                              'height' => $large_image[2],
                            ]
                        ];
                    }
                    elseif ($src = self::extract_image_src($block['innerHTML'])) {
                        $blockMeta['src'] = $src;
                    }
                    if ($image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true)) {
                        $blockMeta['alt'] = $image_alt;
                    }
                    if ($caption = self::extract_caption($block['innerHTML'])) {
                        $blockMeta['caption'] = $caption;
                    }
                    if (isset($block['attrs']['linkDestination']) && $linkDestination = $block['attrs']['linkDestination']) {
                        $blockMeta['linkDestination'] = $linkDestination;
                        if ($link = self::extract_link($block['innerHTML'])) {
                            $blockMeta['link'] = $link;
                        }
                    }
                    break;

                case 'core-embed/youtube':
                    $url = $block['attrs']['url'];

                    if (strpos($url, 'youtube-nocookie') === false) {
                      $url = str_replace('youtube', 'youtube-nocookie', $url);
                    }

                    $blockMeta = [
                        'url' => $url,
                    ];
                    if ($caption = self::extract_caption($block['innerHTML'])) {
                        $blockMeta['caption'] = $caption;
                    }
                    break;

                case 'core-embed/twitter':
                    $blockMeta = [
                        'url' => $block['attrs']['url'],
                    ];
                    if ($caption = self::extract_caption($block['innerHTML'])) {
                        $blockMeta['caption'] = $caption;
                    }
                    break;

                case 'core/heading':
                    $matches = [];
                    preg_match('/<h([1-6]).*?>(.*?)<\/h([1-6])>/', $block['innerHTML'], $matches);
                    $sizes = [
                        null,
                        'xxl',
                        'xl',
                        'l',
                        'm',
                        'sm',
                        'xs',
                    ];
                    $blockMeta = [
                        'size' => $sizes[$matches[1]],
                        'heading' => $matches[2],
                        'className' => $block['attrs']['className']
                    ];
                    break;

                case 'core/gallery':
                    $imgs = [];
                    if (isset($block['attrs']['ids'])) {
                        foreach ($block['attrs']['ids'] as $img_id) {
                            $small_image = wp_get_attachment_image_src($img_id, 'medium');
                            $medium_image = wp_get_attachment_image_src($img_id, 'medium_large');
                            $large_image = wp_get_attachment_image_src($img_id, 'full');
                            $imgs[] = [
                                'small' => [
                                    'src' => parse_url($small_image[0], PHP_URL_PATH),
                                    'width' => $small_image[1],
                                    'height' => $small_image[2],
                                ],
                                'medium' => [
                                    'src' => parse_url($medium_image[0], PHP_URL_PATH),
                                    'width' => $medium_image[1],
                                    'height' => $medium_image[2],
                                ],
                                'large' => [
                                  'src' => parse_url($large_image[0], PHP_URL_PATH),
                                  'width' => $large_image[1],
                                  'height' => $large_image[2],
                                ],
                                'alt' => get_post_meta($img_id, '_wp_attachment_image_alt', true),
                                'caption' => wp_get_attachment_caption($img_id)
                            ];
                        }
                    }
                    $blockMeta = [
                        'imgs' => $imgs,
                    ];
                    break;

                case 'core/list':
                    $matches = [];
                    preg_match_all('/<li>(.*?)<\/li>/', $block['innerHTML'], $matches);
                    preg_match('/<ol>+/', $block['innerHTML'], $isOrdered);
                    if (count($matches) >= 2 && $items = $matches[1]) {
                        $blockMeta = [
                            'isOrdered' => boolval($isOrdered),
                            'items' => array_map([self::class, 'strip_tags'], $items),
                        ];
                    }
                    break;

                case 'core/audio':
                    $blockMeta = [
                        'src' => wp_get_attachment_url($block['attrs']['id']),
                    ];
                    break;

                case 'core/file':
                    $href = $block['attrs']['href'];
                    $matches = [];
                    preg_match('/<a href="'.str_replace('/', '\/', $href).'">(.*?)<\/a>/', $block['innerHTML'], $matches);
                    $blockMeta = [
                        'href' => $href,
                        'text' => self::strip_tags($matches[1]),
                    ];
                    break;

                case 'core/video':
                    $matches = [];
                    preg_match('/<video (autoplay )?(controls )?(loop )?(muted )?(poster="(.*?)" )?src="(.*?)"( playsinline)?><\/video>/', $block['innerHTML'], $matches);
                    $blockMeta = [
                        'src' => $matches[7],
                    ];
                    if ($poster = $matches[6]) {
                        $blockMeta['poster'] = $poster;
                    }
                    // Video settings
                    $settings = [];
                    if ($matches[1]) {
                        $settings[] = 'autoplay';
                    }
                    if ($matches[2]) {
                        $settings[] = 'controls';
                    }
                    if ($matches[3]) {
                        $settings[] = 'loop';
                    }
                    if ($matches[4]) {
                        $settings[] = 'muted';
                    }
                    if ($matches[8]) {
                        $settings[] = 'playsinline';
                    }
                    if ($settings) {
                        $blockMeta['settings'] = $settings;
                    }
                    if ($caption = self::extract_caption($block['innerHTML'])) {
                        $blockMeta['caption'] = $caption;
                    }
                    break;

                case 'core/code':
                    $matches = [];
                    preg_match('/<code>(.*?)<\/code>/is', $block['innerHTML'], $matches);
                    $blockMeta = [
                        'code' => $matches[1],
                    ];
                    break;

                case 'core/preformatted':
                    $matches = [];
                    preg_match('/<pre class="wp-block-preformatted">(.*?)<\/pre>/is', $block['innerHTML'], $matches);
                    $blockMeta = [
                        'text' => self::strip_tags($matches[1]),
                    ];
                    break;

                case 'core/quote':
                case 'core/pullquote':
                    $matches = [];
                    $regexes = [
                        'core/quote' => '/<blockquote class=\"wp-block-quote\">(.*?)<\/blockquote>/',
                        'core/pullquote' => '/<figure class=\"wp-block-pullquote\"><blockquote>(.*?)<\/blockquote><\/figure>/',
                    ];
                    preg_match($regexes[$block['blockName']], $block['innerHTML'], $matches);
                    if (count($matches) >= 2 && $quoteHTML = $matches[1]) {
                        preg_match_all('/<p>(.*?)<\/p>/', $quoteHTML, $matches);
                        $blockMeta = [
                            'quote' => self::strip_tags(implode('\n', $matches[1])),
                        ];
                        preg_match('/<cite>(.*?)<\/cite>/', $quoteHTML, $matches);
                        if (count($matches) >= 2 && $cite = $matches[1]) {
                            $blockMeta['cite'] = self::strip_tags($cite);
                        }
                    }
                    break;

                case 'core/verse':
                    $matches = [];
                    preg_match('/<pre class="wp-block-verse">(.*?)<\/pre>/is', $block['innerHTML'], $matches);
                    $blockMeta = [
                        'text' => self::strip_tags($matches[1]),
                    ];
                    break;
            }

            // Allow to extend for other blocks, or override values
            $blockMeta = apply_filters(
                'Leoloso\BlockMetadata\Metadata::blockMeta',
                $blockMeta,
                $block['blockName'],
                $block
            );

            if ($blockMeta) {
                $ret[] = [
                    'blockName' => $block['blockName'],
                    'meta' => $blockMeta,
                ];
            }
        }

        return $ret;
    }

    public static function strip_tags($content)
    {
        return strip_tags($content, '<strong><a>');
    }

    public static function extract_caption($innerHTML)
    {
        $matches = [];
        preg_match('/<figcaption>(.*?)<\/figcaption>/', $innerHTML, $matches);
        if (count($matches) >= 2 && $caption = $matches[1]) {
            return self::strip_tags($caption);
        }
        return null;
    }

    public static function extract_link($innerHTML)
    {
        $matches = [];
        preg_match('/<a href="(.*?)">(.*?)<\/a>/', $innerHTML, $matches);
        if (count($matches) >= 2 && $link = $matches[1]) {
            return $link;
        }
        return null;
    }

    public static function extract_image_src($innerHTML)
    {
        $matches = [];
        preg_match('/<img src="(.*?)"/', $innerHTML, $matches);
        if (count($matches) >= 2 && $src = $matches[1]) {
            return $src;
        }
        return null;
    }
}
