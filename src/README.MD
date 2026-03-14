# FRAMEWORK PHILOSOPHY:

- Every **interface** is treated as a **service**
- Interfaces are resolved / instantiated in one of two ways:
  • through the **dependency injection container** keeping in mind that the dependency injection container also supports
  **auto-wiring** mechanism
  • via a **concrete facade class**
- **Auto-wiring** is context-dependent — it uses the **concrete strategies** that are appropriate for the current
  context / layer
- There will be an **auto-wiring configuration / mapping** that is stored as plain PHP code (suitable for opcache) or as
  serialized data
- If only **interface → concrete class** mapping is used (without more advanced configuration), it is treated as a *
  *fallback** resolution rule and is cached
    - The cache will contain a **visible / listable set of cache files**, organized either:
      • by prefix
      • or by type / category with proper **clear** instructions / commands
      • special behaviour / improved cleaning mode available when running in **CLI QA / testing / CI** environment
        • php index.php QA
        • #1 Autoframe\Core\Router\AfrCliQaRouter@run
        • #2 clearCache
        • #3 Clear cache file AfrEnv->getCacheFileName: www.DEV.env.cache.php
    - 