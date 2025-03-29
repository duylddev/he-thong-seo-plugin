# Image Proxy Plugin

A WordPress plugin that allows proxying external images through your domain and uses custom feature image URLs from post metadata.

## Features

- Replaces the default featured image with a proxy URL generated from the post slug
- Proxies external images through your own domain
- Serves external images without redirecting
- Provides a user interface in the post editor to set external image URL

## Installation

1. Upload the `image-proxy-plugin.php` file to your WordPress plugins directory
2. Activate the plugin through the WordPress admin panel
3. After activation, flush permalinks by going to Settings > Permalinks and clicking "Save Changes"

## Usage

### Setting up custom feature images

For each post where you want to use a proxied feature image:

1. Edit your post in the WordPress admin
2. Look for the "Feature Image Proxy Settings" meta box in the sidebar
3. Enter the external image URL in the "Real External Image URL" field
4. The proxy URL is automatically generated based on your post slug

### Example

1. External image: `https://example.com/image1.png`
2. Set "Real External Image URL" to `https://example.com/image1.png`
3. If your post has the slug "hello-world", the proxy URL will be: `https://your-domain.com/featured-image/hello-world.png`

When the post is displayed, the featured image will show the proxy URL instead of the original WordPress featured image.
When someone visits `https://your-domain.com/featured-image/hello-world.png`, they'll see the content from `https://example.com/image1.png`.
