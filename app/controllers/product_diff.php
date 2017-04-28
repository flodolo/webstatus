<?php
namespace Webstatus;

$error_messages = [];
$content_title = '';
$comparison_data = [];

/*
    Read locale and product: if locale is missing it defaults to the
    browser's locale, if product is missing it defaults to 'all'.
*/
if (! in_array($requested_locale, $available_locales)) {
    $error_messages[] = 'This locale is not supported.';
}

if ($requested_product != 'all') {
    $source_type = $webstatus->getSourceType($requested_product);
    if (! isset($available_products[$requested_product])) {
        $error_messages[] = 'This product is not supported';
    } elseif (! in_array($source_type, ['xliff', 'properties', 'ftl'])) {
        $error_messages[] = 'This product doesn’t use XLIFF, PROPERTIES or FTL files.';
    }
} else {
    $error_messages[] = 'No product requested.';
}

// Run the compare script specific for this format
if (empty($error_messages)) {
    $product_data = $webstatus->getSingleProductData($requested_product);
    $content_title = "{$product_data['displayed_name']} - Comparison for {$requested_locale}";
    if (! isset($server_config['storage_path'])) {
        $error_messages[] = 'Missing or broken app/config/config.ini file.';
    } else {
        $base_path = "{$server_config['storage_path']}/{$product_data['repository_name']}/";
        if ($product_data['locale_folder'] != '') {
            $base_path .= "{$product_data['locale_folder']}/";
        }

        foreach ($product_data['source_files'] as $source_file) {
            /*
                Scripts are called xliff_stats.py, properties_stats.py and have
                the same input parameters and output.
            */
            $script_path = __DIR__ . "/../scripts/{$source_type}_stats.py";
            $reference_locale = $webstatus->getReferenceLocale($requested_product);
            $command = "python {$script_path} {$base_path} {$source_file} {$reference_locale} {$requested_locale}";
            $comparison_data += json_decode(shell_exec($command), true);
        }
    }

    // Remove complete files
    foreach ($comparison_data as $file_name => $file_data) {
        $incomplete = $file_data['obsolete'] + $file_data['missing'];
        if ($source_type == 'xliff') {
            $incomplete += $file_data['untranslated'];
        }
        if ($incomplete == 0) {
            unset($comparison_data[$file_name]);
        }
    }
}

print $twig->render(
    'product_diff.twig',
    [
        'content_title'   => $content_title,
        'error_messages'  => $error_messages,
        'comparison_data' => $comparison_data,
        'page_title'      => 'Web Status - Strings Comparison',
    ]
);
