/**
 * Cache request
 *
 * @param  string $url
 *
 * @return string
 */
function getRequestCached($url)
{
    // because sometimes we don't want it cached..
    $enabled = !array_key_exists('nocache', $_GET);

    $hash = hash('crc32', $url);

    if ($enabled && \Framework\Caching\FileBased::isCached('vaultre', $hash, 3600)) {
        return \Framework\Caching\FileBased::get('vaultre', $hash);
    }

    $result = getRequest($url);

    \Framework\Caching\FileBased::save('vaultre', $hash, $result);

    return $result;
}

/**
 * Return the provided variable
 *
 * Or return the value returned by called method
 *
 * @param  mixed $var
 * @param  string $method
 * @param  array $params
 *
 * @return mixed
 */
function tap($var, $method = null, $params = []) {
    if ($method == false) return $var;
    return call_user_func_array([$var, $method], []);
}

class Property extends \ArrayObject
{
    protected $property;

    public function __construct(array $property)
    {
        $this->property = $property;
        parent::__construct($property);
    }

    /**
     * Get next open house schedule
     *
     * @return string
     */
    public function getOpenHouseSchedule()
    {
        $property = $this->property;
		
		if ($property['saleLifeId'] == false) {
			return "Contact us for the next open home";
		}

        $url = sprintf("/properties/%s/sale/%s/openHomes", $property['id'], $property['saleLifeId']);
        $response = getRequestCached($url);
        $openhomes = json_decode($response, true);
        $openhome = array_shift($openhomes);

        if ($openhome == false) {
            return "Contact us for the next open home";
        }

        if (!isset($openhome['start']) || !isset($openhome['end'])) {
            return "Contact us for the next open home";
        }

        $start = strtotime($openhome['start']);
        $end = strtotime($openhome['end']);

        if (date('Y/m/d H:i', $start) <= date("Y/m/d H:i")) {
            return "Contact us for the next open home";
        }

        $offset = "+11 hour";
        $startOffset = strtotime($offset, $start);
        $endOffset = strtotime($offset, $end);

        $startDate = date('d/m/Y H:i A', $startOffset);
        $endDate = date('H:i A', $endOffset);

         return sprintf("%s - %s", $startDate, $endDate);
    }

    /**
     * Get largest thumbnail
     *
     * @return string
     */
    public function getLargestThumbnail()
    {
        $property = $this->property;

        foreach ($property['photos'] as $photo) {
            if ($photo['url'] == false) {
                continue;
            }

            return array_pop($photo['thumbnails']);
        }

        return "https://via.placeholder.com/2048x1365.png?text=Property+Image+Coming+Soon";
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return str_replace("\n", "<br>", $this->property['description']);
    }

    /**
     * Check if property is auctioned
     *
     * @return bool
     */
    public function isAuctioned()
    {
        $property = $this->property;
        $searchPrice = strtolower($property['searchPrice']);
        $displayPrice = strtolower($property['displayPrice']);

        return (($searchPrice == 'auction' || $displayPrice == 'auction') && $property['auctionDetails']['dateTime'] != null);
    }

    /**
     * Get property auction schedule
     *
     * @return string|null
     */
    public function getAuctionSchedule()
    {
        if ($this->isAuctioned()) {
            return date('d/m/Y H:i A', strtotime($this->property['auctionDetails']['dateTime']));
        }

        return null;
    }

    /**
     * Get property price
     *
     * @return string
     */
    public function getPrice()
    {
        $currency = "$";
        setlocale(LC_MONETARY, 'en_US');

        if ($this->isAuctioned()) {
            return 'Auction';
        }
		
		if($this->property['displayAddress'] == "\"Orange Grove\" 898 Orange Grove Road, Gunnedah NSW"){
			return "Expressions of Interest";
		}
		
        if ($this->property['searchPrice'] == null) {
            return "Contact us for an updated price";
        }

        return sprintf("%s%s", $currency, number_format($this->property['searchPrice']));
    }

    /**
     * Get auction venue
     *
     * @return string
     */
    public function getAuctionVenue()
    {
        if ($this->isAuctioned() == false) {
            return "Contact us for more information";
        }

        $venue = !empty($this->property['auctionDetails']['venue']) ? $this->property['auctionDetails']['venue'] : 'TBA';
        return sprintf("Auction Venue: %s", $venue);
    }
}

class PropertiesPage
{
    /**
     * List residential properties for sale
     *
     * @param  array $params
     *
     * @return Property[]
     */
    public function getResidential(array $params = [])
    {
        $defaultParams = [
            'sort' => 'inserted',
            'sortOrder' => 'desc',
            'publishedOnPortals' => '1'
        ];
        $params = array_merge($defaultParams, $params);
        $params = http_build_query($params);

        $response = getRequestCached('/properties/residential/sale?' . $params);
        $result = json_decode($response, true);
        $items = [];

        foreach ($result['items'] as $item) {
            if (in_array($item['status'], ['appraisal', 'prospect', 'unconditional'])) {
                continue;
            }

            $items[] = new Property($item);
        }

        return $items;
    }

    /**
     * Get residential properties for lease
     *
     * @param  array $params
     *
     * @return Property[]
     */
    public function getResidentialsForLease(array $params = [])
    {
        $defaultParams = [
            'sort' => 'inserted',
            'sortOrder' => 'desc',
            'publishedOnPortals' => '1'
        ];
        $params = array_merge($defaultParams, $params);
        $params = http_build_query($params);

        $response = getRequestCached('/properties/residential/lease?' . $params);
        $result = json_decode($response, true);
        $items = [];

        foreach ($result['items'] as $item) {
            if (in_array($item['status'], ['appraisal', 'prospect']) || $item['available'] != true) {
                continue;
            }

            $items[] = new Property($item);
        }

        return $items;
    }

    /**
     * Get residential property for sale
     *
     * @param  int $id
     *
     * @return Property
     */
    public function getResidentialProperty($id)
    {
        $response = getRequestCached('/properties/residential/sale/' . $id);
        $item = json_decode($response, true);

        return new Property($item);
    }

    /**
     * Get residential property for lease
     *
     * @param  int $id
     *
     * @return Property
     */
    public function getResidentialForLease($id)
    {
        $response = getRequestCached('/properties/residential/lease/' . $id);
        $item = json_decode($response, true);

        return new Property($item);
    }

    /**
     * Get rural properties for sale
     *
     * @param  array $params
     *
     * @return Property[]
     */
    public function getRural(array $params = [])
    {
        $defaultParams = [
            'sort' => 'inserted',
            'sortOrder' => 'desc',
            'publishedOnPortals' => '1'
        ];
        $params = array_merge($defaultParams, $params);
        $params = http_build_query($params);

        $response = getRequestCached('/properties/rural/sale?' . $params);
        $result = json_decode($response, true);
        $items = [];

        foreach ($result['items'] as $item) {
            if (in_array($item['status'], ['appraisal', 'prospect', 'unconditional'])) {
                continue;
            }

            $items[] = new Property($item);
        }

        return $items;
    }

    /**
     * Get sold rural properties
     *
     * @param  array $params
     *
     * @return Property[]
     */
    public function getRuralSold(array $params = [])
    {
        $defaultParams = [];
        $params = array_merge($defaultParams, $params);
        $params = http_build_query($params);

        $response = getRequestCached('/properties/rural/sale/sold?' . $params);
        $result = json_decode($response, true);
        $items = [];

        foreach ($result['items'] as $item) {
            if (in_array($item['status'], ['appraisal', 'prospect'])) {
                continue;
            }

            $items[] = new Property($item);
        }

        return $items;
    }

    /**
     * Get rural property for sale
     *
     * @param  int $id
     *
     * @return Property
     */
    public function getRuralProperty($id)
    {
        $response = getRequestCached('/properties/rural/sale/' . $id);
        $item = json_decode($response, true);

        return new Property($item);
    }

    /**
     * Get property details
     *
     * @return array
     */
    public function getDetailKeys()
    {
        return ['bed', 'bath', 'garages'];
    }
}

function propertiesPage()
{
    return new PropertiesPage();
}

?>
