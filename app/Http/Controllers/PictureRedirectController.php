<?php

namespace App\Http\Controllers;

use App\Models\PictureRedirect;

/**
 * /storage/old-name.webp when that file is not there any more: if the picture was renamed, go to the new address (301, so search engines follow).
 * An existing file never gets here: the web server sends it straight away.
 */
class PictureRedirectController extends Controller
{
    public function __invoke(string $path)
    {
        abort_if(str_contains($path, '..'), 404);
        $new = PictureRedirect::target($path);
        abort_if($new === null, 404);

        return redirect(asset('storage/'.$new), 301)->header('Cache-Control', 'public, max-age=86400');
    }
}
