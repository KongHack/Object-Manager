# GCWorld Object Manager

A simple object manager that maintains objects in memory

#### Version
2.4.1



### Annotated ObjectManager Keys

|                   |                  |                                                        |
|-------------------|------------------|--------------------------------------------------------|
| ``@om-method``    | [***required***] | for now, use getObject and getModel                    |
| ``@om-namespace`` | [optional]       | namespace path to use when generating recursively      |
| ``@om-name``      | [optional]       | override for the getter.,Will start as getYourNameHere |
| ``@om-gc``        | [optional]       | integer value for automated garbage collection         |