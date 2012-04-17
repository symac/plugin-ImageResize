<?php
class ImageResizeProcess extends ProcessAbstract
{
    protected $_derivativeTypes = array('fullsize_constraint', 
                                        'thumbnail_constraint', 
                                        'square_thumbnail_constraint');
    
    public function run($constraints)
    {
        // Nouvelle variable qui permet de ne pas reconstruire les images si elles existent 
        // déjà. À mettre à true si on veut utiliser le plugin selon sa fonction originale,
        // à savoir pour reconstruire toutes les images. L'usage avec false permet de charger 
        // des lots de fichiers (via Dropbox) sans générer les vignettes à la volée, et sans pour
        // autant être obligé de regénérer toutes les vignettes à chaque fois, mais simplement le diff
        $SMA_REBUILD = false;
        
        $db = get_db();
        $storage = Zend_Registry::get('storage');
        
        // Only resize images on systems using the filesystem storage adapter.
        if (!($storage->getAdapter() instanceof Omeka_Storage_Adapter_Filesystem)) {
            throw new Exception('The storage adapter is not an instance of Omeka_Storage_Adapter_Filesystem.');
        }
        
        // Sanitize the constraints.
        foreach ($constraints as $key => $value) {
            if (!in_array($key, $this->_derivativeTypes) 
             || !is_numeric($value) 
             || !preg_match('/^[1-9][0-9]*$/', $value)) {
                unset($constraints[$key]);
            }
        }
        
        // Iterate all image files in the archive.
        $sql = "SELECT * FROM {$db->File} WHERE has_derivative_image = 1";
        foreach ($db->query($sql)->fetchAll() as $imageFile) {
            
            // Iterate the constraints.
            foreach ($constraints as $derivativeType => $constraint) {
                
                $filesPath = FILES_DIR . '/' . $imageFile['archive_filename'];
                
                // Rarely a file exists in the database but not in the files 
                // directory. Do not attempt to resize a nonexistant file.
                if (!file_exists($filesPath)) {
                    continue;
                }
                
                // On va tester si le fichier existe déjà
                $sma_src = ARCHIVE_DIR."/";
                switch ($derivativeType) {
                    // Resize square thumbnail.
                    case 'square_thumbnail_constraint':
                        $sma_src .= $storage->getPathByType($imageFile['archive_filename'], 'square_thumbnails');
                        break;
                    // Resize thumbnail.
                    case 'thumbnail_constraint':
                        $sma_src .= $storage->getPathByType($imageFile['archive_filename'], 'thumbnails');
                        break;
                    // Resize fullsize.
                    default:
                        $sma_src .= $storage->getPathByType($imageFile['archive_filename'], 'fullsize');
                        break;
                }
                
                
                // On va générer les vignettes si l'une des deux conditions est réunie : 
                // * la vignette destination n'existe pas
                // * la variable $SMA_REBUILD est à true, on veut donc tout reconstruire.
                if ( (!file_exists($sma_src)) || ($SMA_REBUILD) )
                {
                    switch ($derivativeType) {
                        // Resize square thumbnail.
                        case 'square_thumbnail_constraint':
                            $newFileName = Omeka_File_Derivative_Image::createImage($filesPath, $constraint, 'square_thumbnail');
                            $source = FILES_DIR . '/square_thumbnail_' . $newFileName;
                            $dest = $storage->getPathByType($newFileName, 'square_thumbnails');
                            break;
                        // Resize thumbnail.
                        case 'thumbnail_constraint':
                            $newFileName = Omeka_File_Derivative_Image::createImage($filesPath, $constraint, 'thumbnail');
                            $source = FILES_DIR . '/thumbnail_' . $newFileName;
                            $dest = $storage->getPathByType($newFileName, 'thumbnails');
                            break;
                        // Resize fullsize.
                        default:
                            $newFileName = Omeka_File_Derivative_Image::createImage($filesPath, $constraint, 'fullsize');
                            $source = FILES_DIR . '/fullsize_' . $newFileName;
                            $dest = $storage->getPathByType($newFileName, 'fullsize');
                            break;
                    }
                    
                    // Store the file.
                    $storage->store($source, $dest);
                }
                else
                {
                    _log("Image existe déjà, pas recréée : $sma_src");
                }
            }
        }
    }
}
