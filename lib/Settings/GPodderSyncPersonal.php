<?php
declare(strict_types=1);

namespace OCA\GPodderSync\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class GPodderSyncPersonal implements ISettings {

	public function getForm(): TemplateResponse {
		$response = new TemplateResponse('gpoddersync', 'settings/personal', []);
		
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*');
		$response->setContentSecurityPolicy($csp);
		
		return $response;
	}

	public function getSection(): string {
		return 'gpoddersync';
	}

	public function getPriority(): int {
		return 198;
	}
}
