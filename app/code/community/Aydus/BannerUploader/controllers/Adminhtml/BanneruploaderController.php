<?php

/**
 * Banneruploader Wysiwyg Plugin Config
 *
 * @category   Aydus
 * @package    Aydus_BannerUploader
 * @author     Aydus <davidt@aydus.com>
 */

require('Mage/Adminhtml/controllers/Cms/Wysiwyg/ImagesController.php');

class Aydus_BannerUploader_Adminhtml_BanneruploaderController extends Mage_Adminhtml_Cms_Wysiwyg_ImagesController
{
    /**
     * Load template ajax action
     */
    public function templateAction()
    {
        $result = array();
        
        if ($templateId = (int)$this->getRequest()->getParam('template_id')){
            
            $template = Mage::getModel('aydus_banneruploader/template')->load($templateId);
            
            if ($template && $template->getId()){
                
                $result['error'] = false;
                $result['data'] = $template->getContent();
                
                
            } else {
                $result['error'] = true;
                $result['data'] = 'No template found';
            }
            
        } else {
            
            $result['error'] = true;
            $result['data'] = 'No template id';
            
        }
        
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true)->setBody(Mage::helper('core')->jsonEncode($result));
    }
    
    /**
     * Upload image into wysiwyg folder
     */
    public function uploadAction()
    {
        $result = array();
        
        if ($data = $this->getRequest()->getPost()){
        
            $bannerId = @$data['banner_id'];
            $filename = @$data['filename'];
            $imageData = @$data['image_data'];
            $path = @$data['path'];
            
            if ($filename && $imageData){
        
                try {
                     
                    //filereader always sends the image details, strip it out so we can decode
                    $imageData = substr($imageData, strpos($imageData,'base64,') + 7);
                    $image = base64_decode($imageData);

                    //create/set folder
                    $imageDir = Mage::getBaseDir('media').DS.'wysiwyg';
                    if ($path && is_numeric(strpos($path, '/Storage Root/'))){
                        $path = str_replace('/Storage Root/', '', $path);
                        $filename = $path .DS.$filename;
                    }
                    
                    if (!file_exists($imageDir.DS.$path))
                    {
                        mkdir($imageDir.DS.$path,0775,true);
                    }
                     
                    //upload image
                    $imageFilePath = $imageDir .DS.$filename;
                    
                    $size = file_put_contents($imageFilePath, $image);
                    
                    $result['error'] = ($size > 0) ? false : true;
                    
                    if ($result['error']){
                        $result['data'] = 'Image upload failed';
                    } else {
                        $result['error'] = false;
                        $result['data'] = array(
                            'url' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'wysiwyg/'.$filename,
                            'media' => 'wysiwyg/'.$filename,   
                        );
                    }
                     
                } catch (Exception $e){
        
                    $result['error'] = true;
                    $result['data'] = $e->getMessage();
        
                }
        
            } else {
        
                $result['error'] = true;
                $result['data'] = 'Missing params';
            }
        
        } else {
        
        
            $result['error'] = true;
            $result['data'] = 'No data posted';
        
        }
        
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true)->setBody(Mage::helper('core')->jsonEncode($result));
        
    }
    
    /**
     * Remove a banner image
     */
    public function removeAction()
    {
        $result = array();
        
        if ($data = $this->getRequest()->getPost()){
        
            $bannerId = (int)@$data['banner_id'];
        
            if ($bannerId){
        
                $fileUrl = @$data['file_url'];
        
                if ($fileUrl){
        
                    $banner = Mage::getModel('enterprise_banner/banner')->load($bannerId);
        
                    try {
        
                        $mediaFile = substr($fileUrl, strpos($fileUrl, 'media') );
                        $unlinked = false;

                        if (file_exists($mediaFile)){
                            $unlinked = unlink($mediaFile);
                        }
                        
                        if ($unlinked){
                            $result['error'] = false;
                            $result['data'] = "$mediaFile has been deleted.";
                            
                        } else {
                            
                            $result['error'] = true;
                            $result['data'] = "An error occurred deleting the file.";
                        }
        
                    } catch (Exception $e){
        
                        $result['error'] = true;
                        $result['data'] = $e->getMessage();
        
                    }
        
                } else {
        
                    $result['error'] = true;
                    $result['data'] = 'No file url to delete.';
        
                }
        
            } else {
        
                $result['error'] = true;
                $result['data'] = 'No banner id.';
            }
        
        } else {
        
            $result['error'] = true;
            $result['data'] = 'No data posted.';
        }
        
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true)->setBody(Mage::helper('core')->jsonEncode($result));
        
    
    }    
    
    /**
     * Insert html into textarea with new images
     */
    public function insertAction()
    {
        $result = array();
        
        if ($data = $this->getRequest()->getPost()){
            
            $bannerId = (int)@$data['banner_id'];
            
            if ($bannerId){
                
                $bannerImages = @$data['banner_image'];
                $bannerLinks = @$data['banner_link'];
                
                if (is_array($bannerImages) && count($bannerImages)>0){
                    
                    $banner = Mage::getModel('enterprise_banner/banner')->load($bannerId);
                    
                    $content = $banner->getStoreContent();
                                        
                    //replace tag delimiters with comments
                    $content = str_replace(array('{{','}}'),array('<!--','-->'),$content);
                    //replace double quotes with single quotes
                    $content = preg_replace('/<!--([^>]*)["\']([^>]*)["\']-->/',"<!--$1'$2'-->",$content);
                                                                                
                    try {
                        
                        $doc = new DOMDocument();
                        $doc->loadHTML($content);
                        $doc->preserveWhiteSpace = false;
                        $doc->formatOutput = true;
                        
                        $imgs = $doc->getElementsByTagName('img');
                        $anchors = $doc->getElementsByTagName('a');
                        
                        foreach ($imgs as $i=> $img){
                        
                            if (is_numeric(strpos($img->getAttribute('class'), 'banner-image'))){
                                if ($bannerImages[$i]){
                                
                                    $mediaTag = "<!--media url='".$bannerImages[$i]."'-->";
                                
                                } else {
                                
                                    $mediaTag = $img->getAttribute('src');
                                }
                                
                                $img->setAttribute('src',$mediaTag);                                
                            }
                            
                            $anchor = $anchors->item($i);
                            
                            if (is_numeric(strpos($anchor->getAttribute('class'), 'banner-link'))){
                                
                                if ($bannerLinks[$i]){
                                    
                                    $baseUrl = Mage::getBaseUrl();
                                    $bannerLink = trim($bannerLinks[$i]);
                                    $directUrl = str_replace($baseUrl, '', $bannerLink);
                                
                                    $urlTag = "<!--store direct_url='".$directUrl."'-->";
                                
                                } else {
                                
                                    $urlTag = $anchor->getAttribute('href');
                                }
                                
                                $anchor->setAttribute('href',$urlTag);
                            }
                                                        
                        }
                         
                        $body = $doc->getElementsByTagName('body')->item(0);
                        
                        $newHtml = $doc->saveHTML($body);
                        
                        //remove converted carriage returns
                        $newHtml = str_replace('&#13;','',$newHtml);
                        
                        //put back double quotes in commented tags
                        $newHtml = preg_replace("/<!--([^>]*)[']([^>]*)[']-->/",'<!--$1"$2"-->',$newHtml);
                        
                        //put back template delimiters
                        $newHtml = str_replace(array('<!--','-->'), array('{{','}}'), $newHtml);
                        
                        //remove body tag
                        $newHtml = str_replace(array('<body>','</body>'), '', $newHtml);
                                                
                        $result['error'] = false;
                        $result['data'] = $newHtml;
                        
                        
                    } catch (Exception $e){
                        
                        $result['error'] = true;
                        $result['data'] = $e->getMessage();
                        
                    }
                                              
                } else {
                    
                    $result['error'] = true;
                    $result['data'] = 'No banner images';
                    
                }

            } else {
                
                $result['error'] = true;
                $result['data'] = 'No banner id';
            }
            
        } else {
        
            $result['error'] = true;
            $result['data'] = 'No data posted';
        }
        
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true)->setBody(Mage::helper('core')->jsonEncode($result));
    }
    
    /**
     * @todo 
     */
    public function saveAction()
    {
        
        
    }


}
