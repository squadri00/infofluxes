<?php

namespace App\Http\Controllers;

use App\Models\Clip;
use App\Support\SiteControls;

/** The Clips page: a big player that plays the clips one after the other, and six thumbnails to choose from. */
class ClipsController extends Controller
{
    public function __invoke()
    {
        abort_unless(SiteControls::on('clips_page'), 404);

        $clips = Clip::playable()->get()->filter->exists()->map->forPlayer()->values()->all();

        return view('clips', ['clips' => $clips]);
    }
}
