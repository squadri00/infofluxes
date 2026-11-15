<?php

namespace App\Filament\Support;

use App\Support\ImageStore;
use Filament\Forms\Components\RichEditor;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The text editor of the admin for the long texts (articles, stories, pages, short answers).
 * Buttons: bold, italic, underline, strike, sub- and superscript, link, clear formatting; headings and lead and small text;
 * text colour and highlight; alignment; quote, lists, line, folding box, code; tables; pictures; undo and redo.
 * What is typed is cleaned again before a visitor sees it (App\Support\Html::clean): only these parts survive.
 * A picture added inside the text is saved like every picture of the site: as a light WebP.
 */
class Editor
{
    public static function make(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link', 'clearFormatting'],
                ['h2', 'h3', 'h4', 'lead', 'small'],
                ['textColor', 'highlight'],
                ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                ['blockquote', 'bulletList', 'orderedList', 'horizontalRule', 'details', 'code', 'codeBlock'],
                ['table', 'tableAddRowAfter', 'tableAddColumnAfter', 'tableDeleteRow', 'tableDeleteColumn', 'tableMergeCells', 'tableToggleHeaderRow', 'tableDelete'],
                ['attachFiles'],
                ['undo', 'redo'],
            ])
            ->fileAttachmentsDisk(ImageStore::DISK)
            ->fileAttachmentsDirectory('content')
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(ImageStore::TYPES)
            ->fileAttachmentsMaxSize(fn () => ImageStore::maxKb())
            ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file) => ImageStore::store($file, 'content'))
            ->extraInputAttributes(['style' => 'min-height: 9rem;']);
    }
}
