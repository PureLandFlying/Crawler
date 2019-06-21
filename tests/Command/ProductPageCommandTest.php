<?php


namespace App\Tests\Command;


use App\Command\ListPageCommand;
use App\Command\ProductPageCommand;
use App\Processor\ProductReader;
use App\Service\CrawlerService;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ProductPageCommandTest extends KernelTestCase
{
//** @var Application */
    static private $application;
    /** @var Command */
    static private $command;
    /** @var Container */
    static protected $container;

    static private $redis;

    /**
     * @var CrawlerService
     */
    static private $crawlerService;

    /**
     * @var ProductReader
     */
    static private $productReader;

    /*#######################
     # ONCE BEFORE ALL TEST #
     #######################*/
    static public function setUpBeforeClass()
    {
        // Get the kernel
        self::$kernel = self::bootKernel();
        self::$application = new Application(self::$kernel);
        self::$application->setAutoExit(false);
        self::$container = self::$kernel->getContainer();

        self::$crawlerService = self::$container->get('crawler_service');

        // Get Redis
        self::$redis = self::$crawlerService->getRedisService();

        // Get Product Reader
        self::$productReader = self::$crawlerService->getProductReader();

        // Get the commmand
        self::$command = self::$application
            ->find('app:product');
    }

    /**
     * Test the Execute
     */
    public function testExecute(): void
    {
        // SCENARIO
        //  1
        $this->clearCache();

        $this->prepareData(10);

        $crawlerService = $this->getMockBuilder(CrawlerService::class)
            ->setConstructorArgs([self::$container])
            ->setMethods(['getRedisService'])
            ->getMock();
        $crawlerService->expects($this->once())->method("getRedisService")->willReturnCallback(function () {
            return new \App\Tests\Stub\Redis();
        });

        $command = $this->getMockBuilder(ProductPageCommand::class)
            ->setConstructorArgs([self::$container, $crawlerService, self::$productReader])
            ->setMethods()
            ->getMock();

        $commandTester = new CommandTester($command);
        $product_count = 10;
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count])
        );
        $output = $commandTester->getDisplay();

        $this->assertContains('[ERROR] [Queue] redis connect error', $output);

        //-------
        // SCENARIO
        // 2
        $this->clearCache();
        $this->prepareData(10);
        $product_count = 10;
        $command = $this->getMockBuilder(ProductPageCommand::class)
            ->setConstructorArgs([self::$container, self::$crawlerService, self::$productReader])
            ->setMethods(['crawl'])
            ->getMock();
        $redis = self::$redis;

        /**
         * test
         *if(0 === $result) {
         *  $redis->decr('spider:product-count');
         *
         */
        $this->clearCache();
        $this->prepareData(10);
        $command = $this->getMockBuilder(ProductPageCommand::class)
            ->setConstructorArgs([self::$container, self::$crawlerService, self::$productReader])
            ->setMethods(['crawl'])
            ->getMock();
        $command->expects($this->atLeastOnce())->method("crawl")->willReturnCallback(function ($link) use ($redis, $product_count) {
//            $redis->set('spider:product-count', $product_count);
            return ['test same product information'];
        });
        $product_count = 2;
        $commandTester = new CommandTester($command);
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count])
        );
        $output = $commandTester->getDisplay();

        $this->assertContains('[OK] [Product Worker] Got 1 products!', $output);

        /**
         * Test
         *
         * if($this->productCount > 0 &&  $product_count > $this->productCount) {
         *
         */
        $this->clearCache();
        $this->prepareData(10);
        $command = $this->getMockBuilder(ProductPageCommand::class)
            ->setConstructorArgs([self::$container, self::$crawlerService, self::$productReader])
            ->setMethods(['crawl'])
            ->getMock();
        $command->expects($this->atLeastOnce())->method("crawl")->willReturnCallback(function ($link) use ($redis, $product_count) {
            $redis->set('spider:product-count', $product_count);
            return ['test'];
        });
        $product_count = 2;
        $commandTester = new CommandTester($command);
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count])
        );
        $output = $commandTester->getDisplay();

        $this->assertContains('[OK] [Product Worker] Got 0 products!', $output);

        //-----
        // SCENARIO
        //  3

        $commandTester = new CommandTester(self::$command);
        $this->clearCache();

        $i = 0;
        self::$crawlerService->setListProcessStatus('finished');
        self::$redis->sadd('spider:product-links-queue', 'https://www.stadiumgoods.com/uaaaaaaaaa');
        self::$redis->sadd('spider:product-links-queue', '');
        self::$redis->sadd('spider:product-links-queue', 'https://test.stadiumgoods.com/');
        $this->prepareData(3);

        $product_count = 10;
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count])
        );

        $output = $commandTester->getDisplay();

        $this->assertNotContains('[OK] [Product Worker] Got 0 products!', $output);

        //Test if content is well displayed
        $this->assertContains('[OK] [Product Worker] Done！', $output);


        //SCENARIO
        // 3
        $this->clearCache();
        self::$crawlerService->setListProcessStatus('finished');

        $product_count = 10;
        $this->prepareData(10);
        $this->assertEquals(
            ListPageCommand::EXITCODE,
            $commandTester->execute(['--ProductCount' => $product_count])
        );
        $output = $commandTester->getDisplay();

        $this->assertNotContains('[OK] [Product Worker] Got 0 products!', $output);
        $this->assertContains(sprintf('[OK] [Product Worker] Got %d products!', $product_count), $output);
        //Test if content is well displayed
        $this->assertContains('[OK] [Product Worker] Done！', $output);

    }

    function clearCache() {
        self::$redis->del('spider:product-count');
        self::$redis->del('spider:product-all');
        self::$redis->del('spider:product-links-queue');
    }

    private function prepareData($num) {
        $links = [
            "https://www.stadiumgoods.com/nmd-r1-pk-bb2888-grey-tri-blue-red-white",
            "https://www.stadiumgoods.com/crazy-team-k-by3081-mgsogr-cblack-ftwwht",
            "https://www.stadiumgoods.com/nmd-r1-stlt-pk-240980",
            "https://www.stadiumgoods.com/adilette-b41582-cblack-red-green",
            "https://www.stadiumgoods.com/adizero-adios-undftd-b22483-supcol-cblack-ftwwht",
            "https://www.stadiumgoods.com/nmd-cs2-pk-w-ba7212-conavy-conavy-fywwht",
            "https://www.stadiumgoods.com/nmd-cs2-pk-bz0515-cwhite-cblack-cwhite",
            "https://www.stadiumgoods.com/campus-adv-db3191-cgreen-ftwwht-goldmt",
            "https://www.stadiumgoods.com/alphabounce-1-reigning-champ-cg5328-ctan-ctan",
            "https://www.stadiumgoods.com/adidas-hu-nmd-bbc-plaid-green-ef7388",
            "https://www.stadiumgoods.com/falcon-w-d96699-rawgre-rawgre-ltpink",
            "https://www.stadiumgoods.com/nmd-r1-w-208743",
            "https://www.stadiumgoods.com/nmd-r2-by9915-cred-cred-cwhite",
            "https://www.stadiumgoods.com/tubular-doom-sock-pk-by3559-cblack-cblack-traoli",
            "https://www.stadiumgoods.com/nmd-r1-w-cq2013-blue-whiteteal",
            "https://www.stadiumgoods.com/ultraboost-s80732-cred-cwhite-cwhite",
            "https://www.stadiumgoods.com/eqt-cushion-adv-265353",
            "https://www.stadiumgoods.com/ultraboost-uncaged-s82064-trace-cargo-linen-khaki",
            "https://www.stadiumgoods.com/nmd-r1-bd7755-black-black-white",
            "https://www.stadiumgoods.com/ultraboost-pride",
            "https://www.stadiumgoods.com/rs-stan-smith-comfort-badg-bb6886-cblack-cblack-cblack",
            "https://www.stadiumgoods.com/nmd-r2-pk-by9410-running-white-running-white-co",
            "https://www.stadiumgoods.com/raf-simons-stan-smith-cg3351-cwhite-cwhite-cblack",
            "https://www.stadiumgoods.com/k-nemeziz-17-ultraboost",
            "https://www.stadiumgoods.com/ultraboost-bb6179-cblack-cblack",
            "https://www.stadiumgoods.com/solar-hu-glide-w-cg6736-black-multi",
            "https://www.stadiumgoods.com/pw-hu-nmd-nerd-ef2682-blue-red-white",
            "https://www.stadiumgoods.com/eqt-support-mid-adv-pk-db2933-cblack-cblack-goldmt",
            "https://www.stadiumgoods.com/ultraboost-laceless-s80771-navy-green",
            "https://www.stadiumgoods.com/nmd-r1-j-cg6245-black-white",
            "https://www.stadiumgoods.com/rs-stan-smith-f34259-byello-puryel-ftwwht",
            "https://www.stadiumgoods.com/pureboost-x-by8928-black-white",
            "https://www.stadiumgoods.com/eqt-support-adv-by9586-cwhite-cwhite-owhite",
            "https://www.stadiumgoods.com/nmd-racer-juice-db1777-cblack-cgrey",
            "https://www.stadiumgoods.com/iniki-runner-db0055-olive-white",
            "https://www.stadiumgoods.com/ultra-boost-e-g-bc0949-boldblue-red-glow",
            "https://www.stadiumgoods.com/adidas-nmd-bape-neighborhood-stealth-ee9702",
            "https://www.stadiumgoods.com/ultraboost-cg3041-cgrey-cgrey",
            "https://www.stadiumgoods.com/nmd-r2-pk-ba7252-cblack-cwhite-cred",
            "https://www.stadiumgoods.com/nmd-cs2-pk-ba7187-dgsogr-ftwwht-shopin",
            "https://www.stadiumgoods.com/ultraboost-g28965-core-black-running-white-carbo",
            "https://www.stadiumgoods.com/ultraboost-laceless-bb6141-black-grey-white",
            "https://www.stadiumgoods.com/nmd-r1-286964",
            "https://www.stadiumgoods.com/ultraboost-uncaged-258861",
            "https://www.stadiumgoods.com/iniki-runner-by9727-cblack-cblack-gum",
            "https://www.stadiumgoods.com/rising-star-x-r1-g26777-silvmt-colred-ftwwht",
            "https://www.stadiumgoods.com/db-accelerator-tf",
            "https://www.stadiumgoods.com/nmd-xr1-by9922-ftwwht-ftwwht-ftwwht",
            "https://www.stadiumgoods.com/adidas-human-race-nmd-pharrell-bb0619",
            "https://www.stadiumgoods.com/nmd-r1-bd7742-grey-active-blue",
            "https://www.stadiumgoods.com/nmd-r1-w-by3035-utiblk-ftwwht-mgsogr",
            "https://www.stadiumgoods.com/eqt-support-ultra-mmw-cq1826-cblack-cblack-ftwwht",
            "https://www.stadiumgoods.com/i-5923-cq2492-green-ftwwht",
            "https://www.stadiumgoods.com/ultraboost-ltd-220405",
            "https://www.stadiumgoods.com/eqt-support-93-17-yuanxiao-db2571-carbon-cblack-scarlet",
            "https://www.stadiumgoods.com/boost-w-w-w",
            "https://www.stadiumgoods.com/ultra-boost-kolor",
            "https://www.stadiumgoods.com/nmd-r1-stlt-pk-240993",
            "https://www.stadiumgoods.com/ultraboost-w-s80682-cblack-cblack-white",
            "https://www.stadiumgoods.com/superstar-cg6608-ftwwht-reapnk-reapnk",
            "https://www.stadiumgoods.com/ultra-boost-ee3702-dark-green-cloud-white-collegi",
            "https://www.stadiumgoods.com/tubular-doom-ba7554-ftwwht-ftwwht-cblaack",
            "https://www.stadiumgoods.com/ultraboost-ba8143-cgrey-cwhite-cgrey",
            "https://www.stadiumgoods.com/am4ldn-g25950-ftw-shogrn-shogrn",
            "https://www.stadiumgoods.com/bbc-hu-nmd-g26277-cpink-cblue-cpink",
            "https://www.stadiumgoods.com/nmd-r1-ba7245-white-cwhite-white",
            "https://www.stadiumgoods.com/ultraboost-250794",
            "https://www.stadiumgoods.com/ultraboost-ba8920-cblack-black-black",
            "https://www.stadiumgoods.com/pod-s3-1-b37365-grethr-grethr-sorang",
            "https://www.stadiumgoods.com/nmd-r1-w-by9951-cblack-cblue",
            "https://www.stadiumgoods.com/nmd-r1-b37617-grey-two-grey-two-core-black",
            "https://www.stadiumgoods.com/eqt-support-93-17-cq2396-cblack-cblack-ftwwht",
            "https://www.stadiumgoods.com/falcon-w-bb9174-ftwwht-ftwwht-blue",
            "https://www.stadiumgoods.com/pureboost-dpr-ltd-bb6303-core-black-core-black",
            "https://www.stadiumgoods.com/nmd-xr1-pk-ba7215-cnavy-cnavy",
            "https://www.stadiumgoods.com/nmd-r1-s76519-cblack-cblack-cblack",
            "https://www.stadiumgoods.com/ultraboost-bb6059-cgrey-techgrey-white",
            "https://www.stadiumgoods.com/tubular-doom-pk",
            "https://www.stadiumgoods.com/nmd-xr1-by9923-grey-silver-metallic",
            "https://www.stadiumgoods.com/adidas-ultra-boost-packer-black-ef1148",
            "https://www.stadiumgoods.com/3st-003-db3164-cbrown-ftwwht-gum4",
            "https://www.stadiumgoods.com/ultraboost-ltd-220455",
            "https://www.stadiumgoods.com/i-5923-d96608-cblack-cblack-cloudwht",
            "https://www.stadiumgoods.com/nmd-r1-w-b37646-collegiate-burgundy-clear-oran",
            "https://www.stadiumgoods.com/nmd-r1-pk-by1911-cgrey-cwhite",
            "https://www.stadiumgoods.com/nmd-r1-w-b37647-light-granite-clear-orange",
            "https://www.stadiumgoods.com/pod-s3-1-bd7737-cblack-cblack-refsil",
            "https://www.stadiumgoods.com/y-3-runner-4d-i-f99805-red-ftwwht-aergrn",
            "https://www.stadiumgoods.com/iniki-runner-s81010-cblack-cred",
            "https://www.stadiumgoods.com/eqt-support-adv-pk-by9391-ftwwht-ftwwht-subgrn",
            "https://www.stadiumgoods.com/nmd-cs2-pk-cq2372-cblack-cblack",
            "https://www.stadiumgoods.com/nmd-r1-pk-bz0222-cgrey-cpink-cgrey",
            "https://www.stadiumgoods.com/nmd-r1-w-pk-by8763-color-ice-blue-ice-blue-runnin",
            "https://www.stadiumgoods.com/tubular-runner-b25525-cblack-cblack-ftwht",
            "https://www.stadiumgoods.com/nmd-r1-pk-aq0899-black-white",
            "https://www.stadiumgoods.com/city-cup-db3075-ftwwht-cblack-lgsogr",
            "https://www.stadiumgoods.com/dame4-bape-220123",
            "https://www.stadiumgoods.com/eqt-support-93-17-bz0584-cblack-clblack-ftwwht",
            "https://www.stadiumgoods.com/stan-smith-x-pharrell-williams-ac7045-blue-white",
            "https://www.stadiumgoods.com/tubular-instinct-low-by3158-ftwwht-ftwwht-cblack",
            "https://www.stadiumgoods.com/3st-004-f36854-ashgre-cblack-ftwwht",
            "https://www.stadiumgoods.com/ultraboost-w-253874",
            "https://www.stadiumgoods.com/chop-shop-nbhd-da8839-cblack-cblack",
            "https://www.stadiumgoods.com/solar-hu-glide-m-bb8044-white",
            "https://www.stadiumgoods.com/ultraboost-akog-bb7370-grey-white",
            "https://www.stadiumgoods.com/ultraboost-bb6177-ftwwht-ftwwht",
            "https://www.stadiumgoods.com/nmd-r1-pk-b43522-orange-grey-white",
            "https://www.stadiumgoods.com/city-cup-db3076-cblack-cblack-cblack",
            "https://www.stadiumgoods.com/nmd-r1-pk-by1887-black-gum",
            "https://www.stadiumgoods.com/kamanda-01-nbhd-b37341-cblack-cblack-cblack",
            "https://www.stadiumgoods.com/nmd-xr1-pk-by1910-cblack-mgsogr-ftwwht",
            "https://www.stadiumgoods.com/nmd-r2-s-e-cm7879-cblack-cblack",
            "https://www.stadiumgoods.com/ultraboost-m-rchamp",
            "https://www.stadiumgoods.com/ultraboost-bb6171-cblack-cblack",
            "https://www.stadiumgoods.com/nmd-r1-s76518-cwhite-cgrey",
            "https://www.stadiumgoods.com/ultraboost-250879",
            "https://www.stadiumgoods.com/nmd-r1-cq2414-cblack-cblack",
            "https://www.stadiumgoods.com/3st-003-b27820-cblack-lgrani-ftwwht",
            "https://www.stadiumgoods.com/nmd-cs1-pk-ba7209-cblack-cblack-gum",
            "https://www.stadiumgoods.com/nmd-r1-pk-bw1126-cmulti-cmulti",
            "https://www.stadiumgoods.com/nmd-r1-w-pk-bb2363-cpink-cpink-cpink",
            "https://www.stadiumgoods.com/ultraboost-parley-cg3673-carbon-carbon-blue-spirit",
            "https://www.stadiumgoods.com/pw-human-race-nmd-123023",
            "https://www.stadiumgoods.com/adidas-lacombe-donald-glover-eg1763",
            "https://www.stadiumgoods.com/ultraboost-uncaged-m-87447",
            "https://www.stadiumgoods.com/falcon-w",
            "https://www.stadiumgoods.com/nmd-r1-w-pk-bb2364-cblack-cpink",
            "https://www.stadiumgoods.com/ultraboost-m-223358",
            "https://www.stadiumgoods.com/deerupt-runner-b27779-sesosl-cblack-cblack",
            "https://www.stadiumgoods.com/wh-nmd-r2",
            "https://www.stadiumgoods.com/nmd-r1-b79760-cbeige-cbeige",
            "https://www.stadiumgoods.com/ultraboost-uncaged-da9163-carbon-cblack-running-white",
            "https://www.stadiumgoods.com/palace-camton-db2937-ftwwht-cblack-ftwwht",
            "https://www.stadiumgoods.com/eqt-support-mid-mmw-cq1824-cblack-cblack-ftwwht",
            "https://www.stadiumgoods.com/pw-human-race-nmd-tr-ac7031-cream-white-white",
            "https://www.stadiumgoods.com/nmd-r1-pk-w-cq2041-grey-grey-cloud-white",
            "https://www.stadiumgoods.com/predator-precision-tr-ub-cm7913-bluegre-ftwwht-colred",
            "https://www.stadiumgoods.com/nmd-r1-pk-ba8600-grey-white-cream",
            "https://www.stadiumgoods.com/ultraboost-s-e-by2911-ftwwht-ftwwht-black",
            "https://www.stadiumgoods.com/ultraboost-ba8847-cgrey",
            "https://www.stadiumgoods.com/eqt-cushion-adv-cp9458-subgrn-cblack-cwhite",
            "https://www.stadiumgoods.com/ultraboost-w-ba7686-techwhite-cwhite",
            "https://www.stadiumgoods.com/y-3-pureboost-cp9890-cblack-cblack-cblack",
            "https://www.stadiumgoods.com/adidas-ultra-boost-clima-missoni-d97743",
            "https://www.stadiumgoods.com/adidas-ultra-boost-cny-eddie-huang-f36426",
            "https://www.stadiumgoods.com/ultraboost-ltd-cm8272-runningwhite-metallicsilver-ru",
            "https://www.stadiumgoods.com/ultraboost-s82018-colive-colive-cwhite",
            "https://www.stadiumgoods.com/ultraboost-all-terrain-s82036-cblack-ftwwht-cblack",
            "https://www.stadiumgoods.com/3st-002-pk-cg5613-ftwwht-greone-cblack",
            "https://www.stadiumgoods.com/ultraboost-uncaged-bb4486-core-black-multi-color",
            "https://www.stadiumgoods.com/nmd-r1-d96635-white-gum",
            "https://www.stadiumgoods.com/aw-bball-soccer-b43593-borang-ftwwht-cblack",
            "https://www.stadiumgoods.com/nmd-r1-d96616-cblack-cblack",
            "https://www.stadiumgoods.com/ultraboost-ltd-220381",
            "https://www.stadiumgoods.com/ultraboost-m-223286",
            "https://www.stadiumgoods.com/nizza-eg1761-off-white-cloud-white-customiz",
            "https://www.stadiumgoods.com/tubular-doom-s74791-chsogr-metsil-metsil",
            "https://www.stadiumgoods.com/ultraboost-j-191577",
            "https://www.stadiumgoods.com/i-5923-263407",
            "https://www.stadiumgoods.com/nmd-r1-w-pk-cg3601-mintgrn-cblue",
            "https://www.stadiumgoods.com/nmd-r1-bb1969-cblack-cblack-cred",
            "https://www.stadiumgoods.com/swift-run-cq2119-ecrtin-ftwwht-cblack",
            "https://www.stadiumgoods.com/ultraboost-uncaged-da9160-steel-core-black-chalk-pearl",
            "https://www.stadiumgoods.com/new-york-arsham-cm7193-cwhite-cwhite-cwhite",
            "https://www.stadiumgoods.com/alphabounce-m-aramis-b54366-cblack-iron-metallic",
            "https://www.stadiumgoods.com/nmd-xr1-pk-w-205622",
            "https://www.stadiumgoods.com/pw-human-race-nmd-bb0616-red",
            "https://www.stadiumgoods.com/ultraboost-clima-cq0022-cblack-cblack-cblack",
            "https://www.stadiumgoods.com/rs-stan-smith-f34269-tacros-blipnk-ftwwht",
            "https://www.stadiumgoods.com/ultraboost-ltd-220369",
            "https://www.stadiumgoods.com/nmd-r1-w-208726",
            "https://www.stadiumgoods.com/wmns-ultraboost-19-b75881-grey-two-clear-orange-true-ora",
            "https://www.stadiumgoods.com/energy-boost-concepts-bc0236-cwhite-cgreen-cwhite",
            "https://www.stadiumgoods.com/nmd-r1-pk-bb6364-digicamo-cwhite",
            "https://www.stadiumgoods.com/nmd-r1-ac7065-cnavy-cnavy-colive",
            "https://www.stadiumgoods.com/twinstrike-a-d-ac7666-white-cblack-corred",
            "https://www.stadiumgoods.com/adidas-ultra-boost-trace-khaki-cg3039",
            "https://www.stadiumgoods.com/nmd-r1-pk-272440",
            "https://www.stadiumgoods.com/nmd-r2-pk-w-by8782-cpink-cpink",
            "https://www.stadiumgoods.com/ultraboost-bb6165-collegiate-navy-collegiate-nav"
        ];

        $i = 0;
        foreach ($links as $link) {
            if($i++ >= $num) {
                break;
            }
            self::$redis->sadd('spider:product-links-queue', $link);
        }
    }
}