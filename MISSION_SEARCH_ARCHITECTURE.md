# Mission Search System Architecture

## Overview

The Mission search system has been refactored to provide a clean, maintainable, and efficient search experience that integrates Doctrine ORM, Elasticsearch, and Redis caching.

## Architecture Components

### 1. MissionSearchService (`src/Service/MissionSearchService.php`)

This is the core service that handles all mission search operations:

**Responsibilities:**
- Orchestrates search operations between database and Elasticsearch
- Manages caching with Redis
- Handles user access control
- Provides consistent search interface for both regular and full-text search

**Key Methods:**
- `searchMissions()`: Main search method that handles both database and Elasticsearch searches
- `searchWithElasticsearch()`: Handles full-text search using Elasticsearch
- `searchWithDatabase()`: Handles filtered database queries
- `clearMissionCache()`: Invalidates cached results

### 2. MissionSearchRequestHandler (`src/Service/MissionSearchRequestHandler.php`)

Helper service to handle HTTP request processing:

**Responsibilities:**
- Extracts search filters from HTTP requests
- Converts database results to JSON format
- Handles pagination data formatting
- Reduces controller complexity

### 3. MissionController (`src/Controller/MissionController.php`)

Simplified controller that focuses on HTTP handling:

**Key Changes:**
- Removed complex search logic (moved to services)
- Uses dependency injection for services
- Automatic cache invalidation on CRUD operations
- Consistent handling for both index and search endpoints

### 4. Frontend Integration (`assets/js/mission-search-integration.js`)

JavaScript component that provides seamless search experience:

**Features:**
- Real-time search with debouncing
- AJAX-based filtering without page refresh
- Automatic pagination handling
- URL state management
- Progressive enhancement (works without JavaScript)

## Data Flow

### 1. Search Request Flow

```
User Input → Controller → MissionSearchService → Cache Check → Database/Elasticsearch → Response
```

### 2. Cache Strategy

- **Cache Key**: Generated based on search query, filters, user, and pagination
- **Cache Duration**: 10 minutes for filtered results
- **Cache Invalidation**: Automatic on mission create/update/delete
- **Search Bypass**: Full-text searches bypass cache for fresh results

### 3. Search Logic

```php
if (has_search_query) {
    // Use Elasticsearch for full-text search
    elasticsearch_results = searchWithElasticsearch()
    mission_ids = extract_ids(elasticsearch_results)
    missions = fetch_entities_by_ids(mission_ids)
} else {
    // Use database for filtered search
    missions = searchWithDatabase(filters)
}
```

## Key Improvements

### 1. Separation of Concerns
- **Controller**: HTTP handling only
- **Service**: Business logic and search orchestration  
- **Helper**: Request/response processing
- **Frontend**: User interface and AJAX handling

### 2. Performance Optimizations
- Redis caching for frequently accessed data
- Elasticsearch for efficient full-text search
- Debounced search input to reduce server load
- Maintained Elasticsearch relevance ordering

### 3. Maintainability
- Single responsibility principle for each component
- Consistent error handling across all layers
- Comprehensive logging for debugging
- Type hints and documentation

### 4. User Experience
- Seamless integration between search and filtering
- Real-time search without page refreshes
- Proper loading states and error handling
- URL state preservation for bookmarking

## Configuration

### Services Configuration
```yaml
# config/services/mission_services.yaml
services:
    App\Service\MissionSearchService:
        arguments:
            $missionRepository: '@App\Repository\MissionRepository'
            $elasticsearchService: '@App\Service\ElasticsearchService'
            $redisService: '@App\Service\RedisService'
            # ... other dependencies
```

### Cache Configuration
Ensure Redis is configured in your `config/packages/cache.yaml`:

```yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: redis://localhost
```

## Usage Examples

### Backend Usage

```php
// In controller or service
$result = $this->missionSearchService->searchMissions(
    searchQuery: 'urgent delivery',
    filters: ['country' => 'US', 'status' => 'active'],
    sort: ['serviceDate' => 'desc'],
    page: 1,
    userId: $user->getId(),
    isAdmin: $user->hasRole('ROLE_ADMIN')
);

$missions = $result['missions'];
```

### Frontend Usage

```javascript
// Initialize search integration
const missionSearch = new MissionSearchIntegration({
    searchEndpoint: '/mission/search',
    listContainer: '#mission-list',
    searchInput: '#search-input'
});

// Programmatic search
missionSearch.search('urgent', {country: 'US'});
```

## Future Enhancements

1. **Search Analytics**: Track popular search terms and filters
2. **Advanced Filtering**: Add more sophisticated filter options
3. **Search Suggestions**: Implement autocomplete functionality
4. **Export Features**: Allow exporting search results
5. **Saved Searches**: Let users save and reuse search criteria

## Testing

The architecture supports easy testing:

- **Unit Tests**: Each service can be tested independently
- **Integration Tests**: Test the full search flow
- **Frontend Tests**: Test JavaScript components with mock APIs

## Troubleshooting

### Common Issues

1. **Cache not clearing**: Check Redis connection and permissions
2. **Elasticsearch errors**: Verify index exists and mapping is correct
3. **Slow searches**: Check database indexes and Elasticsearch performance
4. **JavaScript errors**: Ensure all DOM elements exist before initialization

### Debugging

Enable debug logging in `config/packages/dev/monolog.yaml`:

```yaml
monolog:
    channels: ['mission_search', 'mission_controller']
    handlers:
        mission_search:
            type: stream
            path: '%kernel.logs_dir%/mission_search.log'
            channels: ['mission_search']
```
