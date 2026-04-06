import React, { useState, useEffect } from 'react';

// Source https://gemini.google.com/app/5ff3dd6a2e5b6fc4?hl=it

// Main App Component
const App = () => {
  const initialData = {
    "seasons": {
      "fallback": "high",
      "range": {
        "low": {
          "0": { "from": "2025-01-01T00:00:00.0Z", "to": "2025-02-15T23:59:59.9Z" },
          "1": { "from": "2025-11-06T00:00:00.0Z", "to": "2025-11-25T23:59:59.9Z" },
          "2": { "from": "2025-07-01T00:00:00.0Z", "to": "2025-07-31T23:59:59.9Z" }
        },
        "high": {
          "0": { "from": "2025-03-01T00:00:00.1Z", "to": "2025-03-31T23:59:59.9Z" },
          "1": { "from": "2026-01-01T13:33:03.969Z", "to": "2026-04-10T13:33:03.969Z" }
        },
        "peak": {
          "0": { "from": "2025-04-15T13:33:03.969Z", "to": "2025-04-30T13:33:03.969Z" },
          "1": { "from": "2025-05-01T13:33:03.969Z", "to": "2025-06-01T13:33:03.969Z" },
          "2": { "from": "2025-06-01T13:33:03.969Z", "to": "2025-06-30T13:33:03.969Z" },
          "3": { "from": "2025-09-01T13:33:03.969Z", "to": "2025-09-30T13:33:03.969Z" }
        }
      }
    }
  };

  const [seasonsData, setSeasonsData] = useState(initialData);
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear().toString());
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingRange, setEditingRange] = useState(null); // null for new, object for edit
  const [seasonTypes, setSeasonTypes] = useState(Object.keys(initialData.seasons.range));

  // Internal state for ranges, easier to manage
  const [ranges, setRanges] = useState([]);

  // Colors for visualization
  const typeColors = {
    low: 'bg-green-400',
    high: 'bg-yellow-400',
    peak: 'bg-red-400',
    default: 'bg-gray-300',
  };

  // Function to convert internal ranges state to the desired JSON format
  const convertRangesToJson = (currentRanges, currentFallback) => {
    const newJsonRanges = {};
    currentSeasonTypes.forEach(type => {
      newJsonRanges[type] = {};
    });

    currentRanges.forEach((range, index) => {
      if (!newJsonRanges[range.type]) {
        newJsonRanges[range.type] = {};
      }
      newJsonRanges[range.type][range.originalIndex !== undefined ? range.originalIndex : Object.keys(newJsonRanges[range.type]).length] = {
        from: range.from,
        to: range.to,
      };
    });

    // Clean up empty season types if they are not in the original data
    const cleanedJsonRanges = {};
    for (const type in newJsonRanges) {
      if (Object.keys(newJsonRanges[type]).length > 0) {
        cleanedJsonRanges[type] = newJsonRanges[type];
      }
    }

    return {
      seasons: {
        fallback: currentFallback,
        range: cleanedJsonRanges,
      },
    };
  };

  // Function to convert initial JSON to internal ranges state
  const convertJsonToRanges = (json) => {
    const flatRanges = [];
    if (json.seasons && json.seasons.range) {
      for (const type in json.seasons.range) {
        if (json.seasons.range.hasOwnProperty(type)) {
          const typeRanges = json.seasons.range[type];
          for (const key in typeRanges) {
            if (typeRanges.hasOwnProperty(key)) {
              const range = typeRanges[key];
              const fromDate = new Date(range.from);
              const toDate = new Date(range.to);
              flatRanges.push({
                id: `${type}-${key}-${Math.random().toString(36).substr(2, 9)}`, // Unique ID for React keys
                originalIndex: key, // Keep original index for stable JSON output
                from: range.from,
                to: range.to,
                type: type,
                year: fromDate.getFullYear().toString(), // Extract year from 'from' date
              });
            }
          }
        }
      }
    }
    return flatRanges;
  };

  // Initialize ranges and season types from initialData
  useEffect(() => {
    const flatRanges = convertJsonToRanges(initialData);
    setRanges(flatRanges);
    setSeasonTypes(Object.keys(initialData.seasons.range));
  }, []);

  // Filter ranges based on selected year
  const filteredRanges = ranges.filter(range => range.year === selectedYear);

  const currentFallback = seasonsData.seasons.fallback;
  const currentSeasonTypes = Object.keys(seasonsData.seasons.range);

  const handleAddOrUpdateRange = (newRange) => {
    const { id, from, to, type, year } = newRange;

    // Basic validation: ensure 'from' is before 'to'
    if (new Date(from) >= new Date(to)) {
      alert("Error: 'From' date must be before 'To' date.");
      return;
    }

    if (id) {
      // Update existing range
      setRanges(prevRanges => {
        const updated = prevRanges.map(r => r.id === id ? newRange : r);
        const updatedJson = convertRangesToJson(updated, currentFallback);
        setSeasonsData(updatedJson);
        return updated;
      });
    } else {
      // Add new range
      const rangeWithId = { ...newRange, id: Math.random().toString(36).substr(2, 9) };
      setRanges(prevRanges => {
        const updated = [...prevRanges, rangeWithId];
        const updatedJson = convertRangesToJson(updated, currentFallback);
        setSeasonsData(updatedJson);
        return updated;
      });
    }
    setIsModalOpen(false);
    setEditingRange(null);
  };

  const handleDeleteRange = (idToDelete) => {
    setRanges(prevRanges => {
      const updated = prevRanges.filter(range => range.id !== idToDelete);
      const updatedJson = convertRangesToJson(updated, currentFallback);
      setSeasonsData(updatedJson);
      return updated;
    });
  };

  const handleFallbackChange = (e) => {
    const newFallback = e.target.value;
    setSeasonsData(prevData => ({
      ...prevData,
      seasons: {
        ...prevData.seasons,
        fallback: newFallback,
      },
    }));
  };

  const handleAddSeasonType = (newType) => {
    if (newType && !seasonTypes.includes(newType)) {
      setSeasonTypes(prevTypes => [...prevTypes, newType]);
      setSeasonsData(prevData => ({
        ...prevData,
        seasons: {
          ...prevData.seasons,
          range: {
            ...prevData.seasons.range,
            [newType]: {}, // Add empty object for the new type
          },
        },
      }));
    }
  };

  const handleExportJson = () => {
    // The seasonsData state already holds the correctly formatted JSON
    const jsonOutput = JSON.stringify(seasonsData, null, 2);
    // For demonstration, we'll log it and offer to copy
    console.log(jsonOutput);
    document.execCommand('copy'); // Copy to clipboard
    alert("JSON copied to clipboard and logged to console!");
  };

  // Helper to get day of year for visualization
  const getDayOfYear = (dateString) => {
    const date = new Date(dateString);
    const start = new Date(date.getFullYear(), 0, 0);
    const diff = (date - start) + ((start.getTimezoneOffset() - date.getTimezoneOffset()) * 60 * 1000);
    const oneDay = 1000 * 60 * 60 * 24;
    return Math.floor(diff / oneDay);
  };

  // Get total days in selected year for visualization scaling
  const daysInYear = new Date(parseInt(selectedYear), 1, 29).getMonth() === 1 ? 366 : 365; // Leap year check

  return (
    <div className="p-6 max-w-4xl mx-auto font-inter">
      <h1 className="text-3xl font-bold mb-6 text-gray-800">Season Range Configurator</h1>

      {/* Year Selector */}
      <div className="mb-6 flex items-center space-x-4">
        <label htmlFor="year-select" className="text-lg font-medium text-gray-700">Select Year:</label>
        <select
          id="year-select"
          className="p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
          value={selectedYear}
          onChange={(e) => setSelectedYear(e.target.value)}
        >
          {Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i).map(year => (
            <option key={year} value={year.toString()}>{year}</option>
          ))}
        </select>
      </div>

      {/* Fallback Season */}
      <div className="mb-6">
        <label htmlFor="fallback-select" className="block text-lg font-medium text-gray-700 mb-2">Fallback Season:</label>
        <select
          id="fallback-select"
          className="p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 w-full md:w-auto"
          value={seasonsData.seasons.fallback}
          onChange={handleFallbackChange}
        >
          {seasonTypes.map(type => (
            <option key={type} value={type}>{type.charAt(0).toUpperCase() + type.slice(1)}</option>
          ))}
        </select>
      </div>

      {/* Visual Timeline */}
      <div className="mb-8 p-4 border border-gray-200 rounded-lg shadow-sm bg-white">
        <h2 className="text-xl font-semibold mb-4 text-gray-800">Yearly Timeline ({selectedYear})</h2>
        <div className="relative h-10 bg-gray-100 rounded-full overflow-hidden">
          {/* Fallback season background */}
          <div className={`absolute inset-0 ${typeColors[currentFallback] || typeColors.default} opacity-70`}></div>

          {/* Render each range on the timeline */}
          {filteredRanges.map(range => {
            const startDay = getDayOfYear(range.from);
            const endDay = getDayOfYear(range.to);
            const left = (startDay / daysInYear) * 100;
            const width = ((endDay - startDay) / daysInYear) * 100;

            if (width <= 0) return null; // Avoid rendering invalid ranges

            return (
              <div
                key={range.id}
                className={`absolute h-full rounded-full ${typeColors[range.type] || typeColors.default} opacity-90`}
                style={{ left: `${left}%`, width: `${width}%` }}
                title={`${range.type.charAt(0).toUpperCase() + range.type.slice(1)}: ${new Date(range.from).toLocaleDateString()} - ${new Date(range.to).toLocaleDateString()}`}
              ></div>
            );
          })}
        </div>
        <div className="flex justify-between text-xs text-gray-600 mt-2">
          <span>Jan 1</span>
          <span>Jul 1</span>
          <span>Dec 31</span>
        </div>
      </div>

      {/* Range Management */}
      <div className="mb-6">
        <h2 className="text-xl font-semibold mb-4 text-gray-800">Defined Ranges for {selectedYear}</h2>
        <button
          onClick={() => { setEditingRange(null); setIsModalOpen(true); }}
          className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 ease-in-out mb-4"
        >
          Add New Range
        </button>

        {filteredRanges.length === 0 ? (
          <p className="text-gray-600">No ranges defined for this year. Click "Add New Range" to start.</p>
        ) : (
          <div className="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Season Type
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    From
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    To
                  </th>
                  <th scope="col" className="relative px-6 py-3">
                    <span className="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredRanges.map((range) => (
                  <tr key={range.id}>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${typeColors[range.type] || typeColors.default} text-gray-800`}>
                        {range.type.charAt(0).toUpperCase() + range.type.slice(1)}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {new Date(range.from).toLocaleString()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {new Date(range.to).toLocaleString()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <button
                        onClick={() => { setEditingRange(range); setIsModalOpen(true); }}
                        className="text-blue-600 hover:text-blue-900 mr-4"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => handleDeleteRange(range.id)}
                        className="text-red-600 hover:text-red-900"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Export JSON Button */}
      <div className="mt-8">
        <button
          onClick={handleExportJson}
          className="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 ease-in-out"
        >
          Export JSON (Copy to Clipboard)
        </button>
      </div>

      {/* Range Form Modal */}
      {isModalOpen && (
        <RangeFormModal
          onClose={() => { setIsModalOpen(false); setEditingRange(null); }}
          onSave={handleAddOrUpdateRange}
          initialRange={editingRange ? { ...editingRange, year: selectedYear } : { from: '', to: '', type: seasonTypes[0], year: selectedYear }}
          seasonTypes={seasonTypes}
          onAddSeasonType={handleAddSeasonType}
        />
      )}
    </div>
  );
};

// Range Form Modal Component
const RangeFormModal = ({ onClose, onSave, initialRange, seasonTypes, onAddSeasonType }) => {
  const [range, setRange] = useState(initialRange);
  const [newSeasonTypeName, setNewSeasonTypeName] = useState('');
  const [showAddTypeInput, setShowAddTypeInput] = useState(false);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setRange(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    onSave(range);
  };

  const handleAddTypeSubmit = () => {
    if (newSeasonTypeName) {
      onAddSeasonType(newSeasonTypeName.toLowerCase());
      setNewSeasonTypeName('');
      setShowAddTypeInput(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 className="text-2xl font-bold mb-6 text-gray-800">{initialRange.id ? 'Edit Season Range' : 'Add New Season Range'}</h2>
        <form onSubmit={handleSubmit}>
          <div className="mb-4">
            <label htmlFor="from" className="block text-sm font-medium text-gray-700">From (Date & Time):</label>
            <input
              type="datetime-local"
              id="from"
              name="from"
              value={range.from ? range.from.substring(0, 16) : ''} // Format for datetime-local input
              onChange={handleChange}
              className="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
          <div className="mb-4">
            <label htmlFor="to" className="block text-sm font-medium text-gray-700">To (Date & Time):</label>
            <input
              type="datetime-local"
              id="to"
              name="to"
              value={range.to ? range.to.substring(0, 16) : ''} // Format for datetime-local input
              onChange={handleChange}
              className="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
          <div className="mb-4">
            <label htmlFor="type" className="block text-sm font-medium text-gray-700">Season Type:</label>
            <select
              id="type"
              name="type"
              value={range.type}
              onChange={handleChange}
              className="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            >
              {seasonTypes.map(type => (
                <option key={type} value={type}>{type.charAt(0).toUpperCase() + type.slice(1)}</option>
              ))}
              <option value="add_new_type">-- Add New Type --</option>
            </select>
            {range.type === 'add_new_type' && (
              <div className="mt-2 flex">
                <input
                  type="text"
                  placeholder="New Season Type Name"
                  value={newSeasonTypeName}
                  onChange={(e) => setNewSeasonTypeName(e.target.value)}
                  className="flex-grow p-2 border border-gray-300 rounded-l-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
                <button
                  type="button"
                  onClick={handleAddTypeSubmit}
                  className="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-r-md"
                >
                  Add
                </button>
              </div>
            )}
          </div>
          <div className="flex justify-end space-x-4 mt-6">
            <button
              type="button"
              onClick={onClose}
              className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md shadow-sm transition duration-300 ease-in-out"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 ease-in-out"
            >
              Save Range
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default App;
