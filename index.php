<?php
/**
 * SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later
 *
 * Empty index.php — PrestaShop convention to prevent directory listings
 * from any module subdirectory exposed under the document root.
 *
 * @author    Eric Osterberg <ejosterberg@gmail.com>
 * @copyright 2026 Eric Osterberg
 * @license   Apache-2.0 OR GPL-2.0-or-later
 */

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Location: ../');
exit;
