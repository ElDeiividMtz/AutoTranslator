<?php
/*
 * AutoTranslator — Blueprint Extension Admin Controller
 * Handles the extension page in /admin/extensions/autotranslator
 * Redirects to the full translation management dashboard.
 *
 * Author: ElDeiividMtz
 * License: MIT
 */

namespace Pterodactyl\Http\Controllers\Admin\Extensions\autotranslator;

use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;

class autotranslatorExtensionController extends Controller
{
    public function __construct(
        private BlueprintExtensionLibrary $blueprint,
    ) {}

    public function index(): View
    {
        return view('admin.extensions.autotranslator.index', [
            'blueprint' => $this->blueprint,
        ]);
    }
}
