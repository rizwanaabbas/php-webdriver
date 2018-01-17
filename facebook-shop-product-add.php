<?php
// An example of using php-webdriver.
namespace Facebook\WebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\LocalFileDetector;

require_once('vendor/autoload.php');
// start Firefox with 5 second timeout
$host = 'http://localhost:4444/wd/hub'; // this is the default
$capabilities = DesiredCapabilities::firefox();
//$capabilities = DesiredCapabilities::chrome();
$driver = RemoteWebDriver::create($host, $capabilities, 5000);

// navigate to 'http://www.seleniumhq.org/'
$driver->get('https://facebook.com');

// adding cookie
$driver->manage()->deleteAllCookies();

$cookie = new Cookie('cookie_name', 'cookie_value');
$driver->manage()->addCookie($cookie);

$cookies = $driver->manage()->getCookies();
print"\n=============Cookies ==================\n";
print_r($cookies);
print"\n=======================================\n";


// wait until the page is loaded
$driver->wait()->until(
    WebDriverExpectedCondition::titleContains('فيسبوك - تسجيل الدخول أو الاشتراك')
);
// print the title of the current page
echo "The title is '" . $driver->getTitle() . "'\n";
// print the URI of the current page
echo "The current URI is '" . $driver->getCurrentURL() . "'\n";
$driver->wait(30);
// write 'php' in the search box
//login if required
$driver->findElement(WebDriverBy::id('email'))
    ->sendKeys('facebook user');
$driver->findElement(WebDriverBy::id('pass'))
    ->sendKeys('facebook password');
// submit the form
$link = $driver->findElement(
    WebDriverBy::id('loginbutton')
);
$link->click();
//load your shop page
$driver->get('https://business.facebook.com/pg/manysolutionsco/shop/?ref=page_internal');
$i = 0;
$products = getProducts();
foreach($products as $product) {
	$pro = array();
	if($i > 0) {
		//print_r($product);
		if($product[7] == 'in stock') {
			$image = $product[4];
			$imageArr = explode('/',$image);
			$imageName = $imageArr[sizeof($imageArr)-1];
			$nameArr = explode('.',$imageName);
			$imageName = $product[0].'.'.$nameArr[sizeof($nameArr)-1];
			//local temp directory with 777 -R to save images
			$imagePath = '/home/admin1/Downloads/productImages/'.$imageName; 
			$price = trim($product[8],' USD');
			$pro['name'] = $product[1];
			if($product[9] != '') {
				$pro['description'] = $product[2];	
			} else {
				$pro['description'] = $product[2];
			}
			$pro['url'] = $product[3];
			$pro['image'] = $imagePath;
			$price = str_replace(',','',$price);
			$pro['price'] = trim($price,' ');
			//load remote image contents
			$image = file_get_contents($image);
			//save remote image contents to local drive 
			file_put_contents($imagePath, $image);
			print_r($pro); 
			saveProduct($driver,$pro);
			sleep(12);
		}
	}
	$i++;
}

//close the Firefox
$driver->quit();

//Save Product
function saveProduct($driver,$product= array()) {
	//open add product form
	try {
	
		$driver->wait(10);
		$link = $driver->findElement(
		    WebDriverBy::id('u_0_1d')
		);
		$link->click();
		//fill add product form
		sleep(1);
		$productName = $product['name'];
		$price =  trim($product['price']);
		$description = substr($product['description'], 0,150);
		$url = $product['url'];
		$filePath = $product['image'];
		$driver->wait()->until(
		  WebDriverExpectedCondition::visibilityOfElementLocated(
		    WebDriverBy::name('productTitle') // locate the $Loader
		  )
		);
		sleep(1);
		$driver->findElement(WebDriverBy::name('productTitle'))
		    ->sendKeys($productName);
		
		//Enter price
		sleep(1);
		$driver->findElement(WebDriverBy::cssSelector('input[placeholder="Enter price"]'))->clear();
		sleep(2);
			$driver->findElement(WebDriverBy::cssSelector('input[placeholder="Enter price"]'))->sendKeys("$price");
		sleep(1);
		
		$driver->findElement(WebDriverBy::name('productDescription'))
		    ->sendKeys($description);
		sleep(1);
		$driver->findElement(WebDriverBy::name('checkoutURL'))
		    ->sendKeys($url);
		
		sleep(2);
		//Add Photos
		$driver->findElement(WebDriverBy::partialLinkText("Add Photos"))->click();
		sleep(1);
		//$fileInput  = $driver->findElement(WebDriverBy::id('js_j'));
		$fileInput = $driver->findElement(WebDriverBy::xpath("//*[contains(@title,'Choose files to upload')]"));
		// set the file detector
		$fileInput->setFileDetector(new LocalFileDetector());
		sleep(1);
		
		//upload the file and submit the form
		//$filePath = '/home/admin1/Downloads/productImages/1096.png';
		//local image path
		$fileInput->sendKeys($filePath);
		sleep(4);
		
		//javascript to click on confirm photo button
		$driver->executeScript('document.getElementsByClassName("layerConfirm")[0].disabled=false; setTimeout(function(){ document.getElementsByClassName("layerConfirm")[0].click();},5000)');
		
		
		
		if($driver->findElement(WebDriverBy::cssSelector('button[action="confirm"]'))->isEnabled()) {
			echo "\nElement Enabled\n";

		} else {
			echo "\nElement disabled\n";
			
		}
		sleep(3);
		//javascript to click on the product save button
		$driver->executeScript('document.getElementsByClassName("layerConfirm")[0].disabled=false; setTimeout(function(){ document.getElementsByClassName("layerButton _4jy0 _4jy3 _4jy1 _51sy")[0].click();},8000)');
		sleep(4);
		echo "\n ".$productName." Product has been saved \n";
	} catch (Exception $e) {
    	//echo 'Caught exception: ',  $e->getMessage(), "\n";
		echo "\n ".$productName." Product has NOT been saved !!!!!!!!!!!!!!\n";
	}	
}

function getProducts() {
	//load products feed from tsv/or CSV
	$feedUrl = "https://example.com/media/facebook_adstoolbox_product_feed.tsv";
	$feed = file_get_contents($feedUrl);
	//parse the content with delimiter
	return parse_csv($feed,"\t");
}

function parse_csv ($csv_string, $delimiter = ",", $skip_empty_lines = true, $trim_fields = true) {
    $enc = preg_replace('/(?<!")""/', '!!Q!!', $csv_string);
    $enc = preg_replace_callback(
        '/"(.*?)"/s',
        function ($field) {
            return urlencode(utf8_encode($field[1]));
        },
        $enc
    );
    $lines = preg_split($skip_empty_lines ? ($trim_fields ? '/( *\R)+/s' : '/\R+/s') : '/\R/s', $enc);
    return  array_map(
        function ($line) use ($delimiter, $trim_fields) {
            $fields = $trim_fields ? array_map('trim', explode($delimiter, $line)) : explode($delimiter, $line);
            return array_map(
                function ($field) {
                    return str_replace('!!Q!!', '"', utf8_decode(urldecode($field)));
                },
                $fields
            );
        },
        $lines
    );
}