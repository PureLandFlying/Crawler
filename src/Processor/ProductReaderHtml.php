<?php

namespace App\Processor;

use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;

class ProductReaderHtml implements ProductReader
{
    private $product;

    function addContent($html) {
        $product = [];
        $crawler = new Crawler();
        $crawler->addHtmlContent($html);
        try {
            $product['name'] = $crawler->filter('#product_addtocart_form .product-name')->text();
        }catch (Exception $e) {}

        $product['images'] = $crawler
            ->filter('#product_addtocart_form .product-gallery-image')
            ->each(function (Crawler $node, $i) {
                $image = '';
                try {
                    $image = $node->filter('img')->attr('data-cfsrc');
                }catch (Exception $e) {}

                return $image;
            });


        $product['skus'] = $crawler
            ->filter('#product_addtocart_form .product-sizes__options .product-sizes__detail')
            ->each(function (Crawler $node, $i) {
                $size = $price = '';
                try {
                    $size = $node->filter('.product-sizes__size')->text();
                }catch (Exception $e) {}

                try {
                    $price = $node->filter('.product-sizes__price .price')->text();
                }catch (Exception $e) {}

                return ['size' => $size, 'price' => $price];
            });

        try {
            $product['manufacturerSku'] = $crawler->filter('#product_addtocart_form #product-attribute-specs-table .data')->first()->text();
        }catch (Exception $e) {}

        $this->product = $product;
    }

    /**
     * @return string
     */
    public function getProuctInfo()
    {
        return $this->product;

    }
}