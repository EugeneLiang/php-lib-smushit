# Smush.it PHP Library

A simple PHP library for accessing the [Yahoo! Smush.it™](http://www.smushit.com/ysmush.it/) lossless image compressor.

## Basic usage

Using Yahoo! Smush.it™ to compress a single local file.

	$smushit = new SmushIt('/path/to/image.png');

Using Yahoo! Smush.it™ to compress a single remote file.

    $smushit = new SmushIt('http://example.org/image.jpg');

Using Yahoo! Smush.it™ to compress multiple files at once.

    $smushit = new SmushIt(array(
    	'/path/to/image.png',
    	'http://example.org/image.jpg'
    ));

Get "Smushed images".

    $smushit->get();
    // Sample result:
    // array (size=1)
    //   0 =>
    //     object(SmushIt)[4]
    //       public 'error' => null
    //       public 'source' => string 'http://example.org/image.jpg' (length=35)
    //       public 'destination' => string 'http://ysmushit.zenfs.com/results/image.jpg' (length=74)
    //       public 'sourceSize' => int 444808
    //       public 'destinationSize' => int 227097
    //       public 'savings' => float 48.94

## Advanced usage

Smush.it PHP Library removes SmushIt objects from get() method result when an error occured during compression process (including "no savings", "image size exceeds 1MB", etc). You can keep these results in get() array by passing `SmushIt::KEEP_ERRORS` flag to SmushIt constructor.

    $images = array(
        'http://example.org/image.jpg',
        'http://example.org/broken-link.jpg'
    );

    $default = new SmushIt($images);
    $keepErr = new SmushIt($images, SmushIt::KEEP_ERRORS);

    count($default->get()); // Value: 1
    count($keepErr->get()); // Value: 2

PHP SmushIt Library can throw exception when an error occured.

    // InvalidArgumentException when calling SmushIt::__construct() with empty $path argument.
    $smushit = new SmushIt(array(
        'http://example.org/image.jpg',
        '', // Throw InvalidArgumentException
        'http://example.org/another-image.jpg' // This image will never be subjected to the API
    ), SmushIt::THROW_EXCEPTION);

## Code Sample

This code sample demonstrates how to compress all `.jpg` files from a directory named `images/` and replace original files by their compressed version.

    // Processing may take a while
    set_time_limit(0);

    // Include Smush.it PHP Library
    require 'SmushIt.class.php';

    // Get all .jpg files from images/ directory
    $files = glob(__DIR__ . '/images/*.jpg');

    // Make batches of 3 images
    $files = array_chunk($files, 3);

    // Take a batch of three files
    foreach($files as $batch) {
        try {
        	// Compress the batch
            $smushit = new SmushIt($batch);
            // And finaly, replace original files by their compressed version
            foreach($smushit->get() as $file) {
                // Sometimes, Smush.it convert files. We don't want that to happen.
                $src = pathinfo($file->source, PATHINFO_EXTENSION);
                $dst = pathinfo($file->destination, PATHINFO_EXTENSION);
                if ($src == $dst AND copy($file->destination, $file->source)) {
                    // Success !
                }
            }
        } catch(Exception $e) {
            continue;
        }
    }
