<?php
/**
 * Copyright 2017 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Google\AdsApi\Examples\AdWords\v201702\Extensions;

require '../../../../vendor/autoload.php';

use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201702\cm\AttributeFieldMapping;
use Google\AdsApi\AdWords\v201702\cm\CampaignFeed;
use Google\AdsApi\AdWords\v201702\cm\CampaignFeedOperation;
use Google\AdsApi\AdWords\v201702\cm\CampaignFeedService;
use Google\AdsApi\AdWords\v201702\cm\Feed;
use Google\AdsApi\AdWords\v201702\cm\FeedAttribute;
use Google\AdsApi\AdWords\v201702\cm\FeedAttributeType;
use Google\AdsApi\AdWords\v201702\cm\FeedItem;
use Google\AdsApi\AdWords\v201702\cm\FeedItemAttributeValue;
use Google\AdsApi\AdWords\v201702\cm\FeedItemGeoRestriction;
use Google\AdsApi\AdWords\v201702\cm\FeedItemOperation;
use Google\AdsApi\AdWords\v201702\cm\FeedItemService;
use Google\AdsApi\AdWords\v201702\cm\FeedMapping;
use Google\AdsApi\AdWords\v201702\cm\FeedMappingOperation;
use Google\AdsApi\AdWords\v201702\cm\FeedMappingService;
use Google\AdsApi\AdWords\v201702\cm\FeedOperation;
use Google\AdsApi\AdWords\v201702\cm\FeedOrigin;
use Google\AdsApi\AdWords\v201702\cm\FeedService;
use Google\AdsApi\AdWords\v201702\cm\GeoRestriction;
use Google\AdsApi\AdWords\v201702\cm\Location;
use Google\AdsApi\AdWords\v201702\cm\MatchingFunction;
use Google\AdsApi\AdWords\v201702\cm\Operator;
use Google\AdsApi\Common\OAuth2TokenBuilder;

/**
 * This example adds a sitelinks feed and associates it with a campaign.
 * To get campaigns, run GetCampaigns.php.
 */
class AddSitelinksUsingFeeds {

  const CAMPAIGN_ID = 'INSERT_CAMPAIGN_ID_HERE';

  // See the Placeholder reference page for a list of all the placeholder types
  // and fields.
  // https://developers.google.com/adwords/api/docs/appendix/placeholders.html
  const PLACEHOLDER_SITELINKS = 1;
  const PLACEHOLDER_FIELD_SITELINK_LINK_TEXT = 1;
  const PLACEHOLDER_FIELD_SITELINK_FINAL_URL = 5;
  const PLACEHOLDER_FIELD_LINE_1_TEXT = 3;
  const PLACEHOLDER_FIELD_LINE_2_TEXT = 4;

  public static function runExample(AdWordsServices $adWordsServices,
      AdWordsSession $session, $campaignId) {
    $sitelinksData = self::createSitelinksFeed($adWordsServices, $session);
    $sitelinksData = self::createSitelinksFeedItems($adWordsServices, $session,
        $sitelinksData);
    self::createSitelinksFeedMapping(
        $adWordsServices, $session, $sitelinksData);
    self::createSitelinksCampaignFeed(
        $adWordsServices, $session, $sitelinksData, $campaignId);
  }

  /**
   * Creates the feed that holds the sitelinks data.
   */
  private static function createSitelinksFeed(AdWordsServices $adWordsServices,
      AdWordsSession $session) {
    $feedService = $adWordsServices->get($session, FeedService::class);

    // Holds the IDs associated to the feeds metadata.
    $sitelinksData = [];

    // Create feed attributes.
    $textAttribute = new FeedAttribute();
    $textAttribute->setType(FeedAttributeType::STRING);
    $textAttribute->setName('Link Text');
    $finalUrlAttribute = new FeedAttribute();
    $finalUrlAttribute->setType(FeedAttributeType::URL_LIST);
    $finalUrlAttribute->setName('Link URL');
    $line1Attribute = new FeedAttribute();
    $line1Attribute->setType(FeedAttributeType::STRING);
    $line1Attribute->setName('Line 1 Description');
    $line2Attribute = new FeedAttribute();
    $line2Attribute->setType(FeedAttributeType::STRING);
    $line2Attribute->setName('Line 2 Description');

    // Create the feed.
    $sitelinksFeed = new Feed();
    $sitelinksFeed->setName('Feed For Sitelinks #' . uniqid());
    $sitelinksFeed->setAttributes(
        [$textAttribute, $finalUrlAttribute, $line1Attribute, $line2Attribute]);
    $sitelinksFeed->setOrigin(FeedOrigin::USER);

    // Create the feed operation and add it on the server.
    $operation = new FeedOperation();
    $operation->setOperator(Operator::ADD);
    $operation->setOperand($sitelinksFeed);
    $result = $feedService->mutate([$operation]);

    // Print out some information about the created feed.
    $savedFeed = $result->getValue()[0];
    $sitelinksData['sitelinksFeedId'] = $savedFeed->getId();
    $savedAttributes = $savedFeed->getAttributes();
    $sitelinksData['linkTextFeedAttributeId'] = $savedAttributes[0]->getId();
    $sitelinksData['linkFinalUrlFeedAttributeId'] =
        $savedAttributes[1]->getId();
    $sitelinksData['line1FeedAttribute'] = $savedAttributes[2]->getId();
    $sitelinksData['line2FeedAttribute'] = $savedAttributes[3]->getId();

    printf(
        "Feed with name '%s', ID %d with linkTextAttributeId %d, "
        . "linkFinalUrlAttributeId %d, line1AttributeId %d and "
        . "line2AttributeId %d was created.\n",
        $savedFeed->getName(),
        $savedFeed->getId(),
        $savedAttributes[0]->getId(),
        $savedAttributes[1]->getId(),
        $savedAttributes[2]->getId(),
        $savedAttributes[3]->getId()
    );

    return $sitelinksData;
  }

  /**
   * Creates sitelinks feed items and add it to the feed.
   */
  private static function createSitelinksFeedItems(
      AdWordsServices $adWordsServices,
      AdWordsSession $session,
      array $sitelinksData
  ) {
    $feedItemService = $adWordsServices->get($session, FeedItemService::class);

    // Create operations to add feed items.
    $home = self::newSitelinkFeedItemAddOperation(
        $sitelinksData,
        'Home',
        'http://www.example.com',
        'Home line 1',
        'Home line 2'
    );
    $stores = self::newSitelinkFeedItemAddOperation(
        $sitelinksData,
        'Stores',
        'http://www.example.com/stores',
        'Stores line 1',
        'Stores line 2'
    );
    $onSale = self::newSitelinkFeedItemAddOperation(
        $sitelinksData,
        'On Sale',
        'http://www.example.com/sale',
        'On Sale line 1',
        'On Sale line 2'
    );
    $support = self::newSitelinkFeedItemAddOperation(
        $sitelinksData,
        'Support',
        'http://www.example.com/support',
        'Support line 1',
        'Support line 2'
    );
    $products = self::newSitelinkFeedItemAddOperation(
        $sitelinksData,
        'Products',
        'http://www.example.com/products',
        'Products line 1',
        'Products line 2'
    );
    // This site link is using geographical targeting by specifying the
    // criterion ID for California.
    $aboutUs = self::newSitelinkFeedItemAddOperation(
        $sitelinksData,
        'About Us',
        'http://www.example.com/about',
        'About Us line 1',
        'About Us line 2',
        21137
    );

    // Add feed item operations on the server and print out some information.
    $result = $feedItemService->mutate(
        [$home, $stores, $onSale, $support, $products, $aboutUs]);
    $sitelinksData['sitelinkFeedItemIds'] = [];

    foreach ($result->getValue() as $feedItem) {
      printf("Feed item with feed item ID %d was added.\n",
          $feedItem->getFeedItemId());
      $sitelinksData['sitelinkFeedItemIds'][] = $feedItem->getFeedItemId();
    }

    return $sitelinksData;
  }

  /**
   * Maps the feed attributes to the sitelink placeholders.
   */
  private static function createSitelinksFeedMapping(
      AdWordsServices $adWordsServices,
      AdWordsSession $session,
      array $sitelinksData
  ) {
    $feedMappingService =
        $adWordsServices->get($session, FeedMappingService::class);

    // Map the feed attribute IDs to the field ID constants.
    $linkTextFieldMapping = new AttributeFieldMapping();
    $linkTextFieldMapping->setFeedAttributeId(
        $sitelinksData['linkTextFeedAttributeId']);
    $linkTextFieldMapping->setFieldId(
        self::PLACEHOLDER_FIELD_SITELINK_LINK_TEXT);
    $linkFinalUrlFieldMapping = new AttributeFieldMapping();
    $linkFinalUrlFieldMapping->setFeedAttributeId(
        $sitelinksData['linkFinalUrlFeedAttributeId']);
    $linkFinalUrlFieldMapping->setFieldId(
        self::PLACEHOLDER_FIELD_SITELINK_FINAL_URL);
    $line1FieldMapping = new AttributeFieldMapping();
    $line1FieldMapping->setFeedAttributeId(
        $sitelinksData['line1FeedAttribute']);
    $line1FieldMapping->setFieldId(self::PLACEHOLDER_FIELD_LINE_1_TEXT);
    $line2FieldMapping = new AttributeFieldMapping();
    $line2FieldMapping->setFeedAttributeId(
        $sitelinksData['line2FeedAttribute']);
    $line2FieldMapping->setFieldId(self::PLACEHOLDER_FIELD_LINE_2_TEXT);

    // Create the feed mapping and feed mapping operation.
    $feedMapping = new FeedMapping();
    $feedMapping->setPlaceholderType(self::PLACEHOLDER_SITELINKS);
    $feedMapping->setFeedId($sitelinksData['sitelinksFeedId']);
    $feedMapping->setAttributeFieldMappings([$linkTextFieldMapping,
        $linkFinalUrlFieldMapping, $line1FieldMapping, $line2FieldMapping]);

    $operation = new FeedMappingOperation();
    $operation->setOperand($feedMapping);
    $operation->setOperator(Operator::ADD);

    // Create the feed mapping operation on the server and print out some
    // information.
    $result = $feedMappingService->mutate([$operation]);
    foreach ($result->getValue() as $feedMapping) {
      printf(
          "Feed mapping with ID %d and placeholder type %d was saved for "
              .  "feed with ID %d.\n",
          $feedMapping->getFeedMappingId(),
          $feedMapping->getPlaceholderType(),
          $feedMapping->getFeedId()
      );
    }
  }

  /**
   * Creates the campaign feed associated to the populated feed data for the
   * specified campaign ID.
   */
  private static function createSitelinksCampaignFeed(
      AdWordsServices $adWordsServices,
      AdWordsSession $session,
      array $sitelinksData,
      $campaignId
  ) {
    $campaignFeedService =
        $adWordsServices->get($session, CampaignFeedService::class);
    $matchingFunctionString = sprintf(
        'AND( IN(FEED_ITEM_ID, {%s}), EQUALS(CONTEXT.DEVICE, "Mobile") )',
        implode(',', $sitelinksData['sitelinkFeedItemIds'])
    );

    // Create a campaign feed and its feed function.
    $campaignFeed = new CampaignFeed();
    $campaignFeed->setFeedId($sitelinksData['sitelinksFeedId']);
    $campaignFeed->setCampaignId($campaignId);

    $matchingFunction = new MatchingFunction();
    $matchingFunction->setFunctionString($matchingFunctionString);
    $campaignFeed->setMatchingFunction($matchingFunction);
    $campaignFeed->setPlaceholderTypes([self::PLACEHOLDER_SITELINKS]);

    // Create the campaign feed operation.
    $operation = new CampaignFeedOperation();
    $operation->setOperand($campaignFeed);
    $operation->setOperator(Operator::ADD);

    // Create the campaign feed on the server and print out some information.
    $result = $campaignFeedService->mutate([$operation]);
    foreach ($result->getValue() as $savedCampaignFeed) {
      printf(
          "Campaign with ID %d was associated with feed with ID %d.\n",
          $savedCampaignFeed->getCampaignId(),
          $savedCampaignFeed->getFeedId()
      );
    }
  }

  /**
   * Creates a site link feed item and wraps it in an ADD operation.
   *
   * @param array $sitelinksData IDs associated to created sitelinks feed
   *     metadata
   * @param string $text the text of the sitelink
   * @param string $finalUrl the final URL of the sitelink
   * @param string $line1 the first line of the sitelink description
   * @param string $line2 the second line of the sitelink description
   * @param int|null $locationId the criterion ID of location to be targeted
   */
  private static function newSitelinkFeedItemAddOperation(
      array $sitelinksData,
      $text,
      $finalUrl,
      $line1,
      $line2,
      $locationId = null
  ) {
    // Create the feed item attribute values for our text values.
    $linkTextAttributeValue = new FeedItemAttributeValue();
    $linkTextAttributeValue->setFeedAttributeId(
        $sitelinksData['linkTextFeedAttributeId']);
    $linkTextAttributeValue->setStringValue($text);
    $linkFinalUrlAttributeValue = new FeedItemAttributeValue();
    $linkFinalUrlAttributeValue->setFeedAttributeId(
        $sitelinksData['linkFinalUrlFeedAttributeId']);
    $linkFinalUrlAttributeValue->setStringValues([$finalUrl]);
    $line1AttributeValue = new FeedItemAttributeValue();
    $line1AttributeValue->setFeedAttributeId(
        $sitelinksData['line1FeedAttribute']);
    $line1AttributeValue->setStringValue($line1);
    $line2AttributeValue = new FeedItemAttributeValue();
    $line2AttributeValue->setFeedAttributeId(
        $sitelinksData['line2FeedAttribute']);
    $line2AttributeValue->setStringValue($line2);

    // Create the feed item.
    $item = new FeedItem();
    $item->setFeedId($sitelinksData['sitelinksFeedId']);
    $item->setAttributeValues([
        $linkTextAttributeValue,
        $linkFinalUrlAttributeValue,
        $line1AttributeValue,
        $line2AttributeValue
    ]);

    // OPTIONAL: Use geographical targeting on a feed.
    // The IDs can be found in the documentation or retrieved with the
    // LocationCriterionService.
    if ($locationId !== null) {
      $location = new Location();
      $location->setId($locationId);
      $item->setGeoTargeting($location);
      $item->setGeoTargetingRestriction(
          new FeedItemGeoRestriction(GeoRestriction::LOCATION_OF_PRESENCE));
    }

    // Create the feed item operation.
    $operation = new FeedItemOperation();
    $operation->setOperand($item);
    $operation->setOperator(Operator::ADD);

    return $operation;
  }

  public static function main() {
    // Generate a refreshable OAuth2 credential for authentication.
    $oAuth2Credential = (new OAuth2TokenBuilder())
        ->fromFile()
        ->build();

    // Construct an API session configured from a properties file and the OAuth2
    // credentials above.
    $session = (new AdWordsSessionBuilder())
        ->fromFile()
        ->withOAuth2Credential($oAuth2Credential)
        ->build();
    self::runExample(
        new AdWordsServices(), $session, intval(self::CAMPAIGN_ID));
  }
}

AddSitelinksUsingFeeds::main();
