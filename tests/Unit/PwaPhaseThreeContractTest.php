<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
class PwaPhaseThreeContractTest extends TestCase {
 public function test_worker_is_versioned_private_safe_and_draft_aware():void {
  $r=dirname(__DIR__,2);$sw=file_get_contents($r.'/public/sw.js');$register=file_get_contents($r.'/resources/js/features/pwa/register-service-worker.js');$draft=file_get_contents($r.'/resources/js/features/accountant/inventory-count.js');$manifest=json_decode(file_get_contents($r.'/public/manifest.webmanifest'),true,512,JSON_THROW_ON_ERROR);
  self::assertSame('standalone',$manifest['display']);self::assertGreaterThanOrEqual(2,count($manifest['shortcuts']));self::assertStringContainsString('WORKER_VERSION',$sw);self::assertStringContainsString("fetch(request, { cache: 'no-store' })",$sw);self::assertStringContainsString('isPrivateApplicationRequest(url) || !isStaticAsset(url)',$sw);self::assertStringContainsString('carled:pwa-prepare-update',$register);self::assertStringContainsString('pending?.push(persistDraft())',$draft);
 }
}
