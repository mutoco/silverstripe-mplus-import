<?php


namespace Mutoco\Mplus\Import\Step;


use Exception;
use GuzzleHttp\Psr7\Header;
use Intervention\Image\ImageManager;
use Mutoco\Mplus\Exception\ImportException;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Serialize\SerializableTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;

class ImportAttachmentStep implements StepInterface
{
    use SerializableTrait;
    use Configurable;

    private static $max_width = 4000;
    private static $max_height = 4000;
    private static $quality = 90;
    private static $folder = 'Import/Images';

    protected string $module;
    protected string $id;

    public function __construct(string $module, string $id)
    {
        $this->module = $module;
        $this->id = $id;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultPriority(): int
    {
        return ImportEngine::PRIORITY_LINK;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        // TODO: Implement activate() method.
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        $config = $engine->getConfig()->getModuleConfig($this->module);
        $field = $config['attachment'] ?? null;
        if (!$field || !isset($config['modelClass'])) {
            throw new ImportException('No attachment field defined or missing modelClass');
        }

        $target = DataObject::get($config['modelClass'])->find('MplusID', $this->id);
        if (!$target || !$target->hasField($field)) {
            throw new ImportException('No target found (field or DataObject missing)');
        }

        $existingFile = $target->getField($field);
        if ($stream = $engine->getApi()->loadAttachment(
            $this->module,
            $this->id,
            function (ResponseInterface $response) use ($existingFile, &$fileName) {
                if ($header = $response->getHeaderLine('Content-Disposition')) {
                    $parts = Header::parse($header);
                    foreach ($parts as $part) {
                        $fileName = $part['filename'] ?? null;
                        if ($fileName) {
                            $fileName = $this->sanitizeFilename($fileName);
                            break;
                        }
                    }
                    if ($existingFile && $existingFile->exists() && $existingFile->Name === $fileName) {
                        throw new Exception('File already exists');
                    }
                }
            }
        )) {
            if ($image = $this->createImage($stream, $fileName)) {
                $target->setField($field, $image);
                $target->write();
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        // TODO: Implement deactivate() method.
    }

    protected function createImage(StreamInterface $stream, string $fileName): ?Image
    {
        try {
            $manager = new ImageManager([
                'driver' => class_exists('Imagick') ? 'imagick' : 'gd'
            ]);
            $img = $manager->make($stream);

            $width = $this->config()->get('max_width');
            $height = $this->config()->get('max_height');
            $quality = $this->config()->get('quality');
            $folderName = $this->config()->get('folder');

            // Resize image to fit into given width/height if larger than max dimensions
            $img->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $image = Image::create();
            $image->setFromString(
                $img->encode('jpg', $quality)->getEncoded(),
                $fileName
            );
            $folder = Folder::find_or_make($folderName);
            if (!$folder->exists() || !$folder->isInDB()) {
                $folder->write();
            }
            $image->ParentID = $folder->ID;
            $image->write();

            AssetAdmin::create()->generateThumbnails($image);
            $image->flushCache();
            $image->write();
            $image->publishRecursive();
            return $image;
        } catch (\Exception $err) {

        }

        return null;
    }

    protected function sanitizeFilename(string $name): string
    {
        return preg_replace('{\.(je?pg|tiff|gif|png|bmp|psd|webp)$}i','.jpg', $name);
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->module = $this->module;
        $obj->id = $this->id;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->module = $obj->module;
        $this->id = $obj->id;
    }
}
