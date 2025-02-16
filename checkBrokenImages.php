<?php 

/**
 * Runs the broken image detection process.
 * Loads WordPress, retrieves posts, checks images, and saves results.
 */
function checkBrokenImages() {
    // Load WordPress environment
    require_once('wp-load.php');

    // File to save the list of broken image links
    $outputFile = 'broken_images.json';

    /**
     * Checks if an image URL is accessible.
     * Uses cURL with a timeout of 5 seconds.
     *
     * @param string $url The image URL to check.
     * @return bool True if the image URL returns HTTP 200, otherwise false.
     */
    function isImageUrlAccessible($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout set to 5 seconds
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Finds broken image links in published WordPress posts.
     * Retrieves posts, extracts image URLs, and checks if they are accessible.
     *
     * @return array List of posts containing broken images.
     */
    function findBrokenImagesInPosts() {
        $brokenImages = [];

        // Query parameters to fetch published posts
        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 500,
            'post_status'    => 'publish',
            'offset'         => 0,
        ];
        $posts = get_posts($args);

        foreach ($posts as $post) {
            $content = $post->post_content;

            // Extract all image URLs from the post content
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);

            if (!empty($matches[1])) {
                $brokenLinks = [];

                // Check each image URL
                foreach ($matches[1] as $imageUrl) {
                    if (!isImageUrlAccessible($imageUrl)) {
                        $brokenLinks[] = $imageUrl;
                    }
                }

                // If broken images are found, store the details
                if (!empty($brokenLinks)) {
                    $brokenImages[$post->ID] = [
                        'post_title'    => $post->post_title,
                        'post_url'      => get_permalink($post->ID),
                        'broken_images' => $brokenLinks,
                    ];
                }
            }

            // usleep(250000); // Uncomment to add a delay between requests if needed
        }

        return $brokenImages;
    }

    // Run the processing function
    $brokenImages = findBrokenImagesInPosts();

    // Save the results in JSON format with proper formatting
    file_put_contents($outputFile, json_encode($brokenImages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    echo "The list of broken images has been saved to {$outputFile}\n";
}

// Execute only if the 'test' GET parameter is set
if (isset($_GET['test'])) {
    checkBrokenImages();
}
