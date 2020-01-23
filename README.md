# GCWorld Object Manager

A simple object manager that maintains objects in memory

#### Version
2.6.1



### Annotated ObjectManager Keys

|                          |                  |                                                                                         |
|--------------------------|------------------|-----------------------------------------------------------------------------------------|
| ``@om-method``           | [***required***] | Options getObject, getModel, getFactoryObject                                           |
| ``@om-namespace``        | [optional]       | Namespace path to use when generating recursively, in the event your namespace is funky |
| ``@om-name``             | [optional]       | Override for the getter. Will start as getYourNameHere                                  |
| ``@om-gc``               | [optional]       | Integer value for automated garbage collection. 0 will not run garbage collection       |
| ``@om-factory-X-method`` | [optional]       | Where X is an integer.  Use with factory-X-arg to setup your static function calls      |
| ``@om-factory-X-arg``    | [optional]       | Where X is an integer.  Use the format of TYPE NAME (ie: string $super_id)              |   

