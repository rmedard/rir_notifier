<?php


namespace Drupal\rir_notifier\Service;


use Drupal;
use Drupal\Core\Entity\EntityTypeManager;
use Mailchimp\Mailchimp;
use Mailchimp\MailchimpLists;

class MailchimpService
{
    protected $entityTypeManager;

    /**
     * MailchimpService constructor.
     * @param $entityTypeManager
     */
    public function __construct(EntityTypeManager $entityTypeManager)
    {
        $this->entityTypeManager = $entityTypeManager;
    }

    public function createList($listId) {
        $APIKey = Drupal::config('mailchimp.settings')->get('api_key');
        $mailchimp = new Mailchimp($APIKey);
        $lists = new MailchimpLists($APIKey);
        $lists->addOrUpdateMember();
        $result = $lists->getList($listId);
        if (isset($result->status) and intval($result->status) == 404) {
            $listObj = json_encode(array());
            $mailchimp->request('POST', 'https://us16.api.mailchimp.com/3.0/lists');
        }
        $term = Drupal\taxonomy\Entity\Term::load(10);
        $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
        if ($termStorage instanceof Drupal\taxonomy\TermStorageInterface) {
            $termStorage->loadParents();
        }
    }



}