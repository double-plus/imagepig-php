# imagepig-php
PHP package for [Image Pig](https://imagepig.com/), the API for AI images.

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
```
