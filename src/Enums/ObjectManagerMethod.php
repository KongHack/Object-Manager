<?php
namespace GCWorld\ObjectManager\Enums;

enum ObjectManagerMethod: string
{
    case GetObject             = 'getObject';
    case GetModel              = 'getModel';
    case GetFactoryObject      = 'getFactoryObject';
    case GetFactoryModelObject = 'getFactoryModelObject';
}
