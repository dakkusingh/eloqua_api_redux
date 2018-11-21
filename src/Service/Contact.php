<?php

namespace Drupal\eloqua_api_redux\Service;


/**
 * Class Contact.
 *
 * @package Drupal\eloqua_api_redux\Service
 */
class Contact {

  /**
   * Eloqua Api Client.
   *
   * @var \Drupal\eloqua_api_redux\Service\EloquaApiClient
   */
  protected $client;

  /**
   * Contact constructor.
   *
   * @param \Drupal\eloqua_api_redux\Service\EloquaApiClient $client
   *   Eloqua Api Client.
   */
  public function __construct(EloquaApiClient $client) {
    $this->client = $client;
  }

  /**
   * See https://docs.oracle.com/cloud/latest/marketingcs_gs/OMCAC/op-api-rest-1.0-data-contact-post.html
   */
  public function createContact(array $contactArray = []) {
//    $endpointUrl = '/api/REST/2.0/data/contact/34729';
//    $endpointUrl = '/api/REST/2.0/data/contacts?page=35';
//    $this->client->doEloquaApiRequest('GET', $endpointUrl, null);

    $endpointUrl = '/api/REST/2.0/data/contact';

//    $contact = $this->dummyContact();
    $contact['emailAddress'] = 'dakku+foobar4@example.org';
    $contact['title'] = 'Mr';
    $contact['firstName'] = 'Dakku';
    $contact['lastName'] = 'Singh';
    $contact = array_filter($contact);

    $this->client->doEloquaApiRequest('POST', $endpointUrl, $contact);
  }

  /**
   * Create a Dummy User array.
   *
   * @return array
   * Dummy User array.
   */
  public function dummyContact() {
    $dummyContact = [
      'accessedAt' => '',
      'accountId' => '',
      'accountName' => '',
      'address1' => '',
      'address2' => '',
      'address3' => '',
      'bouncebackDate' => '',
      'businessPhone' => '',
      'city' => '',
      'country' => '',
      'createdAt' => '',
      'createdBy' => '',
      'currentStatus' => '',
      'depth' => '',
      'description' => '',
      'emailAddress' => '',
      'emailFormatPreference' => '',
      'fax' => '',
      'fieldValues' => '',
      'firstName' => '',
      'id' => '',
      'isBounceback' => '',
      'isSubscribed' => '',
      'lastName' => '',
      'mobilePhone' => '',
      'name' => '',
      'permissions' => '',
      'postalCode' => '',
      'province' => '',
      'salesPerson' => '',
      'subscriptionDate' => '',
      'title' => '',
      'type' => '',
      'unsubscriptionDate' => '',
      'updatedAt' => '',
      'updatedBy' => ''
    ];

    return $dummyContact;
  }

}