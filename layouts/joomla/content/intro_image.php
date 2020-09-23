<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layout
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Utilities\ArrayHelper;

$params   = $displayData->params;
$images   = json_decode($displayData->images);
$imgfloat = empty($images->float_intro) ? $params->get('float_intro') : $images->float_intro;
$image    = parse_url($images->image_intro);
$src      = $image['path'];
$attr     = [];

parse_str($image['query'], $imageParams);

if (count($imageParams))
{
	if ($imageParams['width'] !== 'undefined')
	{
		$attr['width'] = $imageParams['width'] . 'px';
	}

	if ($imageParams['height'] !== 'undefined')
	{
		$attr['height'] = $imageParams['height'] . 'px';
	}
}
?>
<?php if (!empty($images->image_intro)) : ?>
	<figure class="float-<?php echo htmlspecialchars($imgfloat, ENT_COMPAT, 'UTF-8'); ?> item-image">
		<?php if ($params->get('link_titles') && $params->get('access-view')) : ?>
			<a href="<?php echo Route::_(RouteHelper::getArticleRoute($displayData->slug, $displayData->catid, $displayData->language)); ?>">
				<img
					loading="lazy"
					src="<?php echo htmlspecialchars($src, ENT_COMPAT, 'UTF-8'); ?>"
					alt="<?php echo htmlspecialchars($images->image_intro_alt, ENT_COMPAT, 'UTF-8'); ?>"
					itemprop="thumbnailUrl"
					<?php echo ArrayHelper::toString($attr); ?>
				/>
			</a>
		<?php else : ?>
			<img
				loading="lazy"
				src="<?php echo htmlspecialchars($src, ENT_COMPAT, 'UTF-8'); ?>"
				alt="<?php echo htmlspecialchars($images->image_intro_alt, ENT_COMPAT, 'UTF-8'); ?>"
				itemprop="thumbnailUrl"
				<?php echo ArrayHelper::toString($attr); ?>
			/>
		<?php endif; ?>
		<?php if (isset($images->image_intro_caption) && !empty($images->image_intro_caption)) : ?>
			<figcaption class="caption">
				<?php echo htmlspecialchars($images->image_intro_caption, ENT_COMPAT, 'UTF-8'); ?>
			</figcaption>
		<?php endif; ?>
	</figure>
<?php endif; ?>
