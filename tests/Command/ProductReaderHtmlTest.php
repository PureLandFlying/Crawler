<?php


namespace App\Tests\Command;


use PHPUnit\Framework\TestCase;

class ProductReaderHtmlTest extends TestCase
{
    public function testExecute(): void
    {
        $reader = new \App\Processor\ProductReaderHtml();
        $reader->addContent('<html><body><div><div id="product_addtocart_form"><span class="product-gallery-image"></span><span class="product-sizes__options"><span class="product-sizes__detail">
                    <span class="product-sizes__size">5</span>
                    <span class="product-sizes__price">
                        <span class="price" data-flow-localize="item-price">$275.00</span>
                    </span>
                </span></span></div></div></body></html>');
        $product = $reader->getProuctInfo();

        $this->assertArrayHasKey('images', $product);
        $this->assertArrayHasKey('skus', $product);

        $this->assertEquals($product['skus'][0]['size'], 5);
        $this->assertEquals($product['skus'][0]['price'], '$275.00');

        $reader->addContent('<html><body><div><div id="product_addtocart_form"><span class="product-gallery-image"></span><span class="product-sizes__options"><span class="product-sizes__detail">
                    <span class="product-sizes__size_test"></span>
                    <span class="product-sizes__price_test">
                    </span>
                </span></span></div></div></body></html>');
        $product = $reader->getProuctInfo();

        $this->assertArrayHasKey('images', $product);
        $this->assertArrayHasKey('skus', $product);

        $this->assertEquals($product['skus'][0]['size'], '');
        $this->assertEquals($product['skus'][0]['price'], '');
    }
}