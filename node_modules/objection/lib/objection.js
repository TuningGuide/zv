'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.Promise = exports.transaction = exports.ManyToManyRelation = exports.BelongsToOneRelation = exports.HasManyRelation = exports.HasOneRelation = exports.Relation = exports.ValidationError = exports.RelationExpression = exports.QueryBuilderOperation = exports.QueryBuilderBase = exports.QueryBuilder = exports.Model = exports.ModelBase = undefined;

var _ModelBase = require('./model/ModelBase');

var _ModelBase2 = _interopRequireDefault(_ModelBase);

var _Model = require('./model/Model');

var _Model2 = _interopRequireDefault(_Model);

var _QueryBuilderBase = require('./queryBuilder/QueryBuilderBase');

var _QueryBuilderBase2 = _interopRequireDefault(_QueryBuilderBase);

var _QueryBuilder = require('./queryBuilder/QueryBuilder');

var _QueryBuilder2 = _interopRequireDefault(_QueryBuilder);

var _QueryBuilderOperation = require('./queryBuilder/operations/QueryBuilderOperation');

var _QueryBuilderOperation2 = _interopRequireDefault(_QueryBuilderOperation);

var _RelationExpression = require('./queryBuilder/RelationExpression');

var _RelationExpression2 = _interopRequireDefault(_RelationExpression);

var _ValidationError = require('./ValidationError');

var _ValidationError2 = _interopRequireDefault(_ValidationError);

var _Relation = require('./relations/Relation');

var _Relation2 = _interopRequireDefault(_Relation);

var _HasOneRelation = require('./relations/hasOne/HasOneRelation');

var _HasOneRelation2 = _interopRequireDefault(_HasOneRelation);

var _HasManyRelation = require('./relations/hasMany/HasManyRelation');

var _HasManyRelation2 = _interopRequireDefault(_HasManyRelation);

var _BelongsToOneRelation = require('./relations/belongsToOne/BelongsToOneRelation');

var _BelongsToOneRelation2 = _interopRequireDefault(_BelongsToOneRelation);

var _ManyToManyRelation = require('./relations/manyToMany/ManyToManyRelation');

var _ManyToManyRelation2 = _interopRequireDefault(_ManyToManyRelation);

var _transaction = require('./transaction');

var _transaction2 = _interopRequireDefault(_transaction);

var _bluebird = require('bluebird');

var _bluebird2 = _interopRequireDefault(_bluebird);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

exports.ModelBase = _ModelBase2.default;
exports.Model = _Model2.default;
exports.QueryBuilder = _QueryBuilder2.default;
exports.QueryBuilderBase = _QueryBuilderBase2.default;
exports.QueryBuilderOperation = _QueryBuilderOperation2.default;
exports.RelationExpression = _RelationExpression2.default;
exports.ValidationError = _ValidationError2.default;
exports.Relation = _Relation2.default;
exports.HasOneRelation = _HasOneRelation2.default;
exports.HasManyRelation = _HasManyRelation2.default;
exports.BelongsToOneRelation = _BelongsToOneRelation2.default;
exports.ManyToManyRelation = _ManyToManyRelation2.default;
exports.transaction = _transaction2.default;
exports.Promise = _bluebird2.default;


Object.defineProperty(module.exports, "OneToOneRelation", {
  get: function get() {
    console.warn('OneToOneRelation is deprecated and will be removed in version 0.7.0. Use BelongsToOneRelation instead. Simply replace OneToOneRelation with BelongsToOneRelation.');
    return _BelongsToOneRelation2.default;
  }
});

Object.defineProperty(module.exports, "OneToManyRelation", {
  get: function get() {
    console.warn('OneToManyRelation is deprecated and will be removed in version 0.7.0. Use HasManyRelation instead. Simply replace OneToManyRelation with HasManyRelation.');
    return _HasManyRelation2.default;
  }
});