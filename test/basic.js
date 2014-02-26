var should = require('should'),
    DA     = require('../index.js')
    da     = undefined
	ua     = 'DeviceAtlas/test'
	daPath = 'none.json'
    isDone = false
	device = undefined

describe('basic', function() {
    it('create exists', function() {
		da = new DA({ dataPath: daPath})
        should.exist(da)
    })
})

describe('basic', function() {
	da = new DA({ dataPath: daPath})
    should.exist(da)
	da.device(ua, function(error, data) {
		device = data
		isDone = true
	})

    // Polls `someCondition` every 1s
    var check = function(done) {
      if (isDone) done()
      else setTimeout( function(){ check(done) }, 100 )
    }

    before(function( done ){
      check( done )
    });

    it('location returned', function() {
		should.exist(device)
		device.should.have.property('ok')
    })

})
