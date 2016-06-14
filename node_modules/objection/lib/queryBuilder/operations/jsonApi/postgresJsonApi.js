'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});

var _stringify = require('babel-runtime/core-js/json/stringify');

var _stringify2 = _interopRequireDefault(_stringify);

var _lodash = require('lodash');

var _lodash2 = _interopRequireDefault(_lodash);

var _jsonFieldExpressionParser = require('../../parsers/jsonFieldExpressionParser');

var _jsonFieldExpressionParser2 = _interopRequireDefault(_jsonFieldExpressionParser);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

/**
 * @typedef {String} FieldExpression
 *
 * Field expressions allow one to refer to separate JSONB fields inside columns.
 *
 * Syntax: <column reference>[:<json field reference>]
 *
 * e.g. `Person.jsonColumnName:details.names[1]` would refer to value `'Second'`
 * in column `Person.jsonColumnName` which has
 * `{ details: { names: ['First', 'Second', 'Last'] } }` object stored in it.
 *
 * First part `<column reference>` is compatible with column references used in
 * knex e.g. `MyFancyTable.tributeToThBestColumnNameEver`.
 *
 * Second part describes a path to an attribute inside the referred column.
 * It is optional and it always starts with colon which follows directly with
 * first path element. e.g. `Table.jsonObjectColumnName:jsonFieldName` or
 * `Table.jsonArrayColumn:[321]`.
 *
 * Syntax supports `[<key or index>]` and `.<key or index>` flavors of reference
 * to json keys / array indexes:
 *
 * e.g. both `Table.myColumn:[1][3]` and `Table.myColumn:1.3` would access correctly
 * both of the following objects `[null, [null,null,null, "I was accessed"]]` and
 * `{ "1": { "3" : "I was accessed" } }`
 *
 * Caveats when using special characters in keys:
 *
 * 1. `objectColumn.key` This is the most common syntax, good if you are
 *    not using dots or square brackets `[]` in your json object key name.
 * 2. Keys containing dots `objectColumn:[keywith.dots]` Column `{ "keywith.dots" : "I was referred" }`
 * 3. Keys containing square brackets `column['[]']` `{ "[]" : "This is getting ridiculous..." }`
 * 4. Keys containing square brackets and quotes
 *    `objectColumn:['Double."Quote".[]']` and `objectColumn:["Sinlge.'Quote'.[]"]`
 *    Column `{ "Double.\"Quote\".[]" : "I was referred",  "Single.'Quote'.[]" : "Mee too!" }`
 * 99. Keys containing dots, square brackets, single quotes and double quotes in one json key is
 *     not currently supported
 */

exports.default = {
  parseFieldExpression: parseFieldExpression,
  whereJsonbRefOnLeftJsonbValOrRefOnRight: whereJsonbRefOnLeftJsonbValOrRefOnRight,
  whereJsonbRefOnLeftJsonbValOrRefOnRightRawQueryParams: whereJsonbRefOnLeftJsonbValOrRefOnRightRawQueryParams,
  whereJsonFieldRightStringArrayOnLeftQuery: whereJsonFieldRightStringArrayOnLeftQuery,
  whereJsonFieldQuery: whereJsonFieldQuery
};


function parseFieldExpression(expression, extractAsText) {
  var parsed = _jsonFieldExpressionParser2.default.parse(expression);
  var jsonRefs = (0, _lodash2.default)(parsed.access).map('ref').value().join(",");
  var extractor = extractAsText ? '#>>' : '#>';
  var middleQuotedColumnName = parsed.columnName.split('.').join('"."');
  return '"' + middleQuotedColumnName + '"' + extractor + '\'{' + jsonRefs + '}\'';
}

function whereJsonbRefOnLeftJsonbValOrRefOnRight(builder, fieldExpression, operator, jsonObjectOrFieldExpression, queryPrefix) {
  var queryParams = whereJsonbRefOnLeftJsonbValOrRefOnRightRawQueryParams(fieldExpression, operator, jsonObjectOrFieldExpression, queryPrefix);
  return builder.whereRaw.apply(builder, queryParams);
}

function whereJsonbRefOnLeftJsonbValOrRefOnRightRawQueryParams(fieldExpression, operator, jsonObjectOrFieldExpression, queryPrefix) {
  var fieldReference = parseFieldExpression(fieldExpression);

  if (_lodash2.default.isString(jsonObjectOrFieldExpression)) {
    var rightHandReference = parseFieldExpression(jsonObjectOrFieldExpression);
    var refRefQuery = ["(", fieldReference, ")::jsonb", operator, "(", rightHandReference, ")::jsonb"];
    if (queryPrefix) {
      refRefQuery.unshift(queryPrefix);
    }
    return [refRefQuery.join(" ")];
  } else if (_lodash2.default.isObject(jsonObjectOrFieldExpression)) {
    var refValQuery = ["(", fieldReference, ")::jsonb", operator, "?::jsonb"];
    if (queryPrefix) {
      refValQuery.unshift(queryPrefix);
    }
    return [refValQuery.join(" "), (0, _stringify2.default)(jsonObjectOrFieldExpression)];
  }

  throw new Error("Invalid right hand expression.");
}

function whereJsonFieldRightStringArrayOnLeftQuery(builder, fieldExpression, operator, keys) {
  var knex = builder._knex;
  var fieldReference = parseFieldExpression(fieldExpression);
  keys = _lodash2.default.isArray(keys) ? keys : [keys];

  var questionMarksArray = _lodash2.default.map(keys, function (key) {
    if (!_lodash2.default.isString(key)) {
      throw new Error("All keys to find must be strings.");
    }
    return "?";
  });

  var rawSqlTemplateString = "array[" + questionMarksArray.join(",") + "]";
  var rightHandExpression = knex.raw(rawSqlTemplateString, keys);

  return fieldReference + ' ' + operator.replace('?', '\\?') + ' ' + rightHandExpression;
}

function whereJsonFieldQuery(knex, fieldExpression, operator, value) {
  var fieldReference = parseFieldExpression(fieldExpression, true);
  var normalizedOperator = normalizeOperator(knex, operator);

  // json type comparison takes json type in string format
  var cast = void 0;
  var escapedValue = knex.raw(" ?", [value]);
  if (_lodash2.default.isNumber(value)) {
    cast = "::NUMERIC";
  } else if (_lodash2.default.isBoolean(value)) {
    cast = "::BOOLEAN";
  } else if (_lodash2.default.isString(value)) {
    cast = "::TEXT";
  } else if (_lodash2.default.isNull(value)) {
    cast = "::TEXT";
    escapedValue = 'NULL';
  } else {
    throw new Error("Value must be string, number, boolean or null.");
  }

  return '(' + fieldReference + ')' + cast + ' ' + normalizedOperator + ' ' + escapedValue;
}

function normalizeOperator(knex, operator) {
  var trimmedLowerCase = operator.trim().toLowerCase();

  switch (trimmedLowerCase) {
    case "is":
    case "is not":
      return trimmedLowerCase;
    default:
      return knex.client.formatter().operator(operator);
  }
}