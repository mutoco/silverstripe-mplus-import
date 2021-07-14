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
use Psr\Log\LoggerInterface;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
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

    public function getModule(): string
    {
        return $this->module;
    }

    public function getId(): string
    {
        return $this->id;
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
            function (ResponseInterface $response) use ($existingFile, $target, $engine, $field, &$fileName) {
                if ($header = $response->getHeaderLine('Content-Disposition')) {
                    $parts = Header::parse($header);
                    foreach ($parts as $part) {
                        $fileName = $part['filename'] ?? null;
                        if ($fileName) {
                            $fileName = $this->sanitizeFilename($fileName);
                            break;
                        }
                    }

                    $result = $target->invokeWithExtensions(
                        'shouldImportMplusAttachment',
                        $fileName,
                        $field,
                        $this,
                        $engine
                    );

                    if (!empty($result)) {
                        if (min($result) === false) {
                            throw new Exception('File import cancelled');
                        }
                    } else if ($existingFile && $existingFile->exists() && $existingFile->Name === $fileName) {
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
    }

    protected function createImage(StreamInterface $stream, string $fileName): ?Image
    {
        try {
            if (`which convert`) {
                return $this->createImageWithConvert($stream, $fileName);
            } else {
                return $this->createImageWithIntervention($stream, $fileName);
            }
        } catch (\Throwable $err) {
            Injector::inst()->get(LoggerInterface::class)->error(sprintf('Unable to create image from file %s', $fileName));
            Injector::inst()->get(LoggerInterface::class)->error($err->getMessage());
        }

        return null;
    }

    protected function createImageWithConvert(StreamInterface $stream, string $fileName): ?Image
    {
        $stream->rewind();
        $tmpFile = tmpfile();
        $path = stream_get_meta_data($tmpFile)['uri'];
        while (!$stream->eof()) {
            fwrite($tmpFile, $stream->read(262144));
        }
        $stream->close();
        $width = $this->config()->get('max_width');
        $height = $this->config()->get('max_height');
        $outfile = tempnam(sys_get_temp_dir(), 'attachment') . '.jpg';

        exec(sprintf(
            'convert -quiet %s -resize \'%dx%d>\' -colorspace RGB -strip %s',
            $path, $width, $height, $outfile
        ), $result, $exitCode);

        fclose($tmpFile);

        $resultImage = null;
        if ($exitCode === 0) {
            $image = Image::create();
            $image->setFromString(file_get_contents($outfile), $fileName);
            $resultImage = $this->storeImage($image);
        }

        unlink($outfile);
        return $resultImage;
    }

    protected function createImageWithIntervention(StreamInterface $stream, string $fileName): Image
    {
        $manager = new ImageManager([
            'driver' => class_exists('Imagick') ? 'imagick' : 'gd'
        ]);
        $img = $manager->make($stream);

        $width = $this->config()->get('max_width');
        $height = $this->config()->get('max_height');
        $quality = $this->config()->get('quality');


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

        return $this->storeImage($image);
    }

    protected function storeImage(Image $image): Image
    {
        $folderName = $this->config()->get('folder');

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
    }

    protected function sanitizeFilename(string $name): string
    {
        return preg_replace('{\.(jpe?g|tiff?|gif|png|bmp|psd|webp)$}i','', $name) . '.jpg';
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
