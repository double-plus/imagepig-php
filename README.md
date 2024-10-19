# imagepig
[PHP package](https://packagist.org/packages/doubleplus/imagepig) for [Image Pig](https://imagepig.com/), the API for AI images.

## Installation

```
composer require doubleplus/imagepig
```

## Example of usage

```php
use ImagePig\ImagePig;

# create instance of API (put here your actual API key)
$imagepig = ImagePig('your-api-key');

# call the API with a prompt to generate an image
$result = $imagepig->xl('cute piglet running on a green garden');

# save image to a file
$result->save('cute-piglet.jpeg');

# or access image data (binary string)
$result->data;

# or access image as a GDImage object (needs to have the GD extension installed)
$result->image
```

## Contact us
Something does not work as expected? Feel free to [send us a message](https://imagepig.com/contact/), we are here for you.
