#Install
```console
foo@bar:~$ composer global require infira/poesis-mg
foo@bar:~$ pmg /path/to/your/config.yaml
```


#Config
```yaml
connection:
  host: null
  user: null
  pass: null
  db: null

namespace : ''
voidTables : []


model:
  extender: \Infira\Poesis\orm\Model
  prefix : ''
  fileExt: php
  connectionName: defaultPoesisDbConnection
  traits: [ ]
  imports: [ ]
  log : true
  dataMethodsClass: \Infira\Poesis\dr\DataMethods
  dataMethodsClassTraits: []
  makeNode: false
  node:
    className: "%model%Node"
    extender: \Infira\Poesis\orm\Node
    createNodeConstructorParams: ["a","b"]
    traits: [ ]
    imports: [ ]
  columnClass: \Infira\Poesis\orm\ModelColumn

  TIDEnabled: false
  TIDColumName: TID


modelShortcut:
  name: PoesisModelShortcut
  namespace: ''
  fileExt: 'trait.php'
  imports: [ ]

models:
  myModel:
```